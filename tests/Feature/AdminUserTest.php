<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_users_newest_first_with_their_request_count_and_balance(): void
    {
        $admin = $this->admin();
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        Generation::factory()->for($ada)->count(2)->create();
        app(CreditLedger::class)->purchase($ada, 5_000_000_000);
        app(CreditLedger::class)->charge(Generation::factory()->for($ada)->succeeded(providerCost: 100_000_000)->create());
        User::factory()->create(['email' => 'grace@example.com']);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 3)
            ->has('users.data.0', fn (AssertableJson $user) => $user
                ->where('email', 'grace@example.com')
                ->where('generations_count', 0)
                ->where('balance', '0.000000000')
                ->etc())
            ->has('users.data.1', fn (AssertableJson $user) => $user
                ->where('email', 'ada@example.com')
                ->where('generations_count', 3)
                ->where('balance', '4.875000000')
                ->where('is_admin', false)
                ->where('is_active', true)
                ->etc())
            ->where('users.data.2.email', 'admin@example.com'));
    }

    #[TestWith(['lovelace', 'ada@example.com'])]
    #[TestWith(['GRACE@', 'grace@example.com'])]
    public function test_searching_matches_a_name_or_an_email(string $search, string $expectedEmail): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

        $this->actingAs($admin)->get('/admin/users?search='.urlencode($search))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', $expectedEmail)
            ->where('filters.search', $search));
    }

    /**
     * @param  list<string>  $expectedEmails
     */
    #[TestWith(['role=admin', ['admin@example.com']])]
    #[TestWith(['role=user', ['off@example.com', 'on@example.com']])]
    #[TestWith(['status=active', ['on@example.com', 'admin@example.com']])]
    #[TestWith(['status=inactive', ['off@example.com']])]
    #[TestWith(['role=user&status=active', ['on@example.com']])]
    #[TestWith(['role=admin&status=inactive', []])]
    public function test_the_list_can_be_filtered_by_role_and_status_together(string $query, array $expectedEmails): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'on@example.com']);
        User::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->actingAs($admin)->get("/admin/users?{$query}")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('users.data', fn (Collection $users) => $users->pluck('email')->all() === $expectedEmails));
    }

    public function test_the_filters_are_sent_back_for_the_page_to_show(): void
    {
        $this->actingAs($this->admin())->get('/admin/users?role=user&status=inactive&search=ada')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters', ['search' => 'ada', 'role' => 'user', 'status' => 'inactive']));
    }

    #[TestWith(['role=owner', 'role'])]
    #[TestWith(['status=banned', 'status'])]
    public function test_an_unknown_filter_value_is_rejected(string $query, string $field): void
    {
        $this->actingAs($this->admin())->get("/admin/users?{$query}")->assertSessionHasErrors($field);
    }

    public function test_the_user_page_shows_their_credits_requests_and_history(): void
    {
        $admin = $this->admin();
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        $ledger = app(CreditLedger::class);
        $ledger->purchase($admin, 1_000_000_000);
        $ledger->purchase($ada, 10_000_000_000);
        $ledger->charge(Generation::factory()->for($ada)->succeeded(providerCost: 100_000_000)->create());
        Generation::factory()->for($ada)->accepted(GenerationStatus::Failed)->create();
        $inFlight = Generation::factory()->for($ada)->accepted()->create(['estimated_max_price' => 300_000_000]);

        $this->actingAs($admin)->get("/admin/users/{$ada->id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/users/Show')
            ->where('user.email', 'ada@example.com')
            ->where('isCurrentUser', false)
            ->where('userCredits', ['balance' => '9.875000000', 'held' => '0.300000000', 'available' => '9.575000000'])
            ->where('credits.available', '1.000000000')
            ->where('stats', ['generations' => 3, 'succeeded' => 1, 'failed' => 1, 'in_flight' => 1, 'spent' => '0.125000000'])
            ->has('generations', 3)
            ->where('generations.0.id', $inFlight->id)
            ->has('transactions.data', 2)
            ->where('transactions.data.0.type', 'charge'));
    }

    public function test_saving_an_account_updates_it_and_a_new_email_is_unverified(): void
    {
        $admin = $this->admin();
        $ada = User::factory()->create();

        $this->actingAs($admin)->from("/admin/users/{$ada->id}")->patch("/admin/users/{$ada->id}", [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'is_active' => false,
        ])->assertRedirect("/admin/users/{$ada->id}")->assertSessionHasNoErrors();

        $ada->refresh();
        $this->assertSame('Ada Lovelace', $ada->name);
        $this->assertSame('ada@example.com', $ada->email);
        $this->assertFalse($ada->is_active);
        $this->assertNull($ada->email_verified_at);
    }

    public function test_reactivating_an_account_keeps_an_unchanged_email_verified(): void
    {
        $admin = $this->admin();
        $ada = User::factory()->inactive()->create(['name' => 'Ada', 'email' => 'ada@example.com']);

        $this->actingAs($admin)->patch("/admin/users/{$ada->id}", [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $ada->refresh();
        $this->assertTrue($ada->is_active);
        $this->assertNotNull($ada->email_verified_at);
    }

    public function test_an_email_another_account_uses_is_rejected(): void
    {
        $admin = $this->admin();
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        User::factory()->create(['email' => 'grace@example.com']);

        $this->actingAs($admin)->patch("/admin/users/{$ada->id}", [
            'name' => 'Ada',
            'email' => 'grace@example.com',
            'is_active' => true,
        ])->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertSame('ada@example.com', $ada->refresh()->email);
    }

    public function test_admins_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/admin/users/{$admin->id}", [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'is_active' => false,
        ])->assertSessionHasErrors(['is_active' => "You can't deactivate your own account."]);

        $this->assertTrue($admin->refresh()->is_active);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
    }
}
