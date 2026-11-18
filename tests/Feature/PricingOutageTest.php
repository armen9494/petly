<?php

namespace Tests\Feature;

use App\Jobs\SubmitGenerationToPixelforge;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * What users see when GET /v1/pricing is down and nothing is cached.
 */
class PricingOutageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_estimate_returns_503_with_a_message(): void
    {
        $this->failPricing();

        $this->actingAs(User::factory()->create())
            ->getJson('/estimate?characters=10&size=1024x1024&quality=low&reference_images=0')
            ->assertStatus(503)
            ->assertExactJson(['message' => "Pixelforge's prices are unavailable right now."]);
    }

    public function test_a_generation_is_not_saved_or_queued_and_the_user_is_asked_to_retry(): void
    {
        Queue::fake([SubmitGenerationToPixelforge::class]);
        $this->failPricing();
        $user = User::factory()->create();
        CreditTransaction::factory()->for($user)->create(['amount' => Money::fromDollars(1)]);

        $this->actingAs($user)
            ->post('/generations', ['prompt' => 'A cat', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertSessionHasErrors(['pricing' => "We couldn't load Pixelforge's prices just now. Please try again in a moment."]);

        $this->assertSame(0, Generation::query()->count());
        Queue::assertNotPushed(SubmitGenerationToPixelforge::class);
    }

    private function failPricing(): void
    {
        Http::fake(['*/v1/pricing' => Http::response(
            ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.']],
            503,
        )]);
    }
}
