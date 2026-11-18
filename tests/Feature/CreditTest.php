<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_buying_credits_adds_to_the_balance(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/credits', ['dollars' => 10])->assertRedirect();

        $this->assertSame(10_000_000_000, app(CreditLedger::class)->balance($user));
    }

    #[TestWith(['1', 1_000_000_000, '$1.00'])]
    #[TestWith(['12.50', 12_500_000_000, '$12.50'])]
    #[TestWith(['1000', 1_000_000_000_000, '$1000.00'])]
    public function test_any_amount_from_1_to_1000_dollars_with_cents_can_be_bought(string $dollars, int $expectedNanos, string $expectedAmount): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/credits')->post('/credits', ['dollars' => $dollars])
            ->assertRedirect('/credits')
            ->assertInertiaFlash('success', "Added {$expectedAmount} of credits to your account.");

        $this->assertSame($expectedNanos, app(CreditLedger::class)->balance($user));
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'description' => "Bought {$expectedAmount} of credits"]);
    }

    #[TestWith(['', 'Choose or enter an amount.'])]
    #[TestWith(['0.99', 'You can buy between $1 and $1,000 at a time.'])]
    #[TestWith(['1000.01', 'You can buy between $1 and $1,000 at a time.'])]
    #[TestWith(['12.345', 'Enter a dollar amount with up to two decimal places, like 20 or 12.50.'])]
    #[TestWith(['-5', 'Enter a dollar amount with up to two decimal places, like 20 or 12.50.'])]
    #[TestWith(['1e3', 'Enter a dollar amount with up to two decimal places, like 20 or 12.50.'])]
    public function test_an_amount_out_of_range_or_not_in_dollars_and_cents_is_rejected(string $dollars, string $expectedError): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/credits', ['dollars' => $dollars])
            ->assertSessionHasErrors(['dollars' => $expectedError]);

        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_the_balance_is_purchases_minus_charges_and_holds_are_separate(): void
    {
        $user = User::factory()->create();
        app(CreditLedger::class)->purchase($user, 5_000_000_000);
        $charged = Generation::factory()->for($user)->succeeded(providerCost: 100_000_000)->create();
        app(CreditLedger::class)->charge($charged);
        Generation::factory()->for($user)->accepted()->create(['estimated_max_price' => 300_000_000]);

        $this->actingAs($user)->get('/credits')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('web/Credits')
            ->where('credits.balance', '4.875000000')
            ->where('credits.held', '0.300000000')
            ->where('credits.available', '4.575000000')
            ->has('transactions.data', 2));
    }

    public function test_charging_a_generation_twice_records_one_charge(): void
    {
        $generation = Generation::factory()->succeeded()->create();

        app(CreditLedger::class)->charge($generation);
        app(CreditLedger::class)->charge($generation);

        $this->assertSame(1, CreditTransaction::query()->count());
    }
}
