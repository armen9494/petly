<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationToPixelforge;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePixelforge();
        Queue::fake();
    }

    public function test_it_saves_the_generation_with_its_estimate_and_queues_it(): void
    {
        $user = $this->userWithCredits(1);

        $this->actingAs($user)->post('/generations', [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', '', 'https://example.com/b.png'],
        ])->assertRedirect('/');

        $generation = Generation::query()->sole();
        $this->assertTrue(Str::isUuid($generation->uuid));
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame(['https://example.com/a.png', 'https://example.com/b.png'], $generation->reference_images);
        $this->assertSame(467_343_750, $generation->estimated_max_price);
        $this->assertSame(357_781_250, $generation->estimated_min_price);

        Queue::assertPushed(SubmitGenerationToPixelforge::class, fn ($job) => $job->generation->is($generation));
    }

    public function test_it_blocks_a_generation_the_user_cannot_afford(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/generations', ['prompt' => 'A cat', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertSessionHasErrors('credits');

        $this->assertSame(0, Generation::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_unfinished_generations_hold_their_maximum_price(): void
    {
        $user = User::factory()->create();
        CreditTransaction::factory()->for($user)->create(['amount' => 100_000_000]);
        Generation::factory()->for($user)->create(['estimated_max_price' => 60_000_000]);

        // $0.10 minus the $0.06 hold leaves $0.04: too little for a high image
        // (up to $0.34), enough for a low one (up to $0.0218).
        $this->actingAs($user)
            ->post('/generations', ['prompt' => 'A cat', 'size' => '1024x1024', 'quality' => 'high'])
            ->assertSessionHasErrors('credits');

        $this->actingAs($user)
            ->post('/generations', ['prompt' => 'A cat', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertSessionHasNoErrors();
    }

    public function test_it_validates_the_request(): void
    {
        $user = $this->userWithCredits(1);

        $this->actingAs($user)->post('/generations', [
            'prompt' => str_repeat('a', 4001),
            'size' => '512x512',
            'quality' => 'ultra',
            'reference_images' => ['ftp://example.com/a.png'],
        ])->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images.0']);

        $this->actingAs($user)->post('/generations', [
            'prompt' => 'A cat',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => array_fill(0, 5, 'https://example.com/a.png'),
        ])->assertSessionHasErrors('reference_images');
    }

    public function test_the_prompt_limit_counts_characters_not_bytes(): void
    {
        $this->actingAs($this->userWithCredits(1))->post('/generations', [
            'prompt' => str_repeat('é', 4000),
            'size' => '1024x1024',
            'quality' => 'low',
        ])->assertSessionHasNoErrors();
    }

    public function test_the_estimate_endpoint_prices_from_pixelforge_rates(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/estimate?characters=400&size=1536x1024&quality=high&reference_images=2')
            ->assertOk()
            ->assertJson([
                'min_price' => Money::toDecimal(357_781_250),
                'max_price' => '0.467343750',
                'tokens' => ['max' => ['input_text_tokens' => 115, 'reasoning_tokens' => 8000]],
            ]);
    }

    public function test_the_studio_shows_the_users_own_generations(): void
    {
        $user = User::factory()->create();
        Generation::factory()->for($user)->succeeded()->create();
        Generation::factory()->create();

        $this->actingAs($user)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('web/Studio')
            ->has('generations.data', 1)
            ->where('generations.data.0.price', '0.062500000')
            ->where('hasGenerationsInFlight', false));
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_it_tells_the_user_how_many_reference_images_are_allowed(): void
    {
        $this->actingAs($this->userWithCredits(1))->post('/generations', [
            'prompt' => 'A cat',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => array_fill(0, 5, 'https://example.com/a.png'),
        ])->assertSessionHasErrors(['reference_images' => 'You can add at most 4 reference images.']);
    }

    public function test_it_tells_the_user_reference_images_must_be_web_urls(): void
    {
        $this->actingAs($this->userWithCredits(1))->post('/generations', [
            'prompt' => 'A cat',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => ['ftp://example.com/a.png'],
        ])->assertSessionHasErrors(['reference_images.0' => 'Reference images must be http or https URLs.']);
    }

    public function test_the_estimate_endpoint_rejects_options_pixelforge_does_not_offer(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/estimate?characters=10&size=512x512&quality=ultra&reference_images=5')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size', 'quality', 'reference_images']);
    }

    public function test_estimates_reuse_pixelforges_prices_instead_of_fetching_them_each_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/estimate?characters=10&size=1024x1024&quality=low&reference_images=0')->assertOk();
        $this->actingAs($user)->getJson('/estimate?characters=20&size=1024x1024&quality=high&reference_images=1')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_the_studio_keeps_polling_while_a_generation_is_unfinished(): void
    {
        $user = User::factory()->create();
        Generation::factory()->for($user)->succeeded()->create();
        Generation::factory()->for($user)->create(['status_detail' => 'Waiting for a free slot: Pixelforge runs 3 generations at a time.']);

        $this->actingAs($user)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hasGenerationsInFlight', true)
            ->where('generations.data.0.status', 'pending')
            ->where('generations.data.0.status_detail', 'Waiting for a free slot: Pixelforge runs 3 generations at a time.'));
    }

    public function test_a_generation_saved_while_the_queue_is_down_is_kept_and_the_user_is_told_it_will_start(): void
    {
        Exceptions::fake();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Connection refused [tcp://127.0.0.1:6379]'));
        $user = $this->userWithCredits(1);

        $this->actingAs($user)
            ->followingRedirects()
            ->post('/generations', ['prompt' => 'A cat', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('web/Studio')
                ->hasFlash('success', 'Your image is saved and will start in a few minutes. You will only be charged once it succeeds.'));

        $generation = Generation::query()->sole();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame('Delayed on our side. It will be sent to Pixelforge within a few minutes.', $generation->status_detail);
        Exceptions::assertReported(RuntimeException::class);
    }

    private function userWithCredits(int $dollars): User
    {
        $user = User::factory()->create();
        CreditTransaction::factory()->for($user)->create(['amount' => Money::fromDollars($dollars)]);

        return $user;
    }
}
