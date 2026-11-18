<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\WebhookEvent;
use ErrorException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PixelforgeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_succeeded_event_records_the_actual_cost_and_charges_the_user(): void
    {
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->accepted(GenerationStatus::Processing)->create();

        $this->postWebhook($this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded')))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(1100, $generation->output_tokens);
        $this->assertSame(56_540_000, $generation->provider_cost);
        $this->assertSame(70_675_000, $generation->price);
        $this->assertNotNull($generation->image_url);

        $charge = CreditTransaction::query()->sole();
        $this->assertSame(CreditTransactionType::Charge, $charge->type);
        $this->assertSame(-70_675_000, $charge->amount);
        $this->assertSame($generation->user_id, $charge->user_id);
        Queue::assertPushed(StoreGenerationImage::class, fn (StoreGenerationImage $job) => $job->generation->is($generation));
    }

    public function test_a_redelivered_event_is_recorded_and_charged_only_once(): void
    {
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->accepted(GenerationStatus::Processing)->create();
        $event = $this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded'));

        $this->postWebhook($event)->assertNoContent();
        $this->postWebhook($event)->assertNoContent();

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(1, CreditTransaction::query()->count());
        Queue::assertPushedTimes(StoreGenerationImage::class, 1);
    }

    public function test_two_different_success_events_for_one_generation_charge_once(): void
    {
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->accepted(GenerationStatus::Processing)->create();
        $remote = $this->remoteGeneration($generation, 'succeeded');

        $this->postWebhook($this->webhookEvent('generation.succeeded', $remote))->assertNoContent();
        $this->postWebhook($this->webhookEvent('generation.succeeded', $remote))->assertNoContent();

        $this->assertSame(2, WebhookEvent::query()->count());
        $this->assertSame(1, CreditTransaction::query()->count());
        Queue::assertPushedTimes(StoreGenerationImage::class, 1);
    }

    public function test_a_late_processing_event_never_moves_a_succeeded_generation_back(): void
    {
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->accepted()->create();

        $this->postWebhook($this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded')))->assertNoContent();
        $this->postWebhook($this->webhookEvent('generation.processing', $this->remoteGeneration($generation->refresh(), 'processing')))->assertNoContent();

        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
        $this->assertNotNull($generation->image_url);
    }

    public function test_a_failed_generation_is_free(): void
    {
        $generation = Generation::factory()->accepted(GenerationStatus::Processing)->create();

        $this->postWebhook($this->webhookEvent('generation.failed', $this->remoteGeneration($generation, 'failed')))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('generation_failed', $generation->error_code);
        $this->assertNull($generation->price);
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_an_event_that_beats_the_create_response_is_matched_by_metadata(): void
    {
        // Saved, but the POST /v1/generations response hasn't come back yet.
        $generation = Generation::factory()->create();
        $remote = $this->remoteGeneration($generation, 'processing');

        $this->postWebhook($this->webhookEvent('generation.processing', $remote))->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Processing, $generation->status);
        $this->assertSame($remote['id'], $generation->pixelforge_id);
    }

    public function test_it_rejects_a_bad_signature(): void
    {
        $generation = Generation::factory()->accepted()->create();
        $event = $this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded'));

        $this->postWebhook($event, secret: 'whsec_someone_else')->assertStatus(400);

        $this->assertSame(GenerationStatus::Queued, $generation->refresh()->status);
        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_it_rejects_an_old_timestamp_to_stop_replays(): void
    {
        $generation = Generation::factory()->accepted()->create();
        $event = $this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded'));

        $this->postWebhook($event, timestamp: time() - 301)->assertStatus(400);

        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_it_rejects_a_missing_signature(): void
    {
        $this->postJson('/webhooks/pixelforge', ['id' => 'evt_1', 'type' => 'ping'])->assertStatus(400);
    }

    public function test_it_acknowledges_a_ping(): void
    {
        $this->postWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => ['object' => []]])
            ->assertNoContent();
    }

    public function test_it_acknowledges_events_for_generations_it_does_not_know(): void
    {
        $remote = $this->remoteGeneration(Generation::factory()->make(['uuid' => 'not-ours']), 'succeeded');

        $this->postWebhook($this->webhookEvent('generation.succeeded', $remote))->assertNoContent();

        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_a_failed_event_after_success_never_undoes_the_charge(): void
    {
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->accepted(GenerationStatus::Processing)->create();

        $this->postWebhook($this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded')))->assertNoContent();
        $this->postWebhook($this->webhookEvent('generation.failed', $this->remoteGeneration($generation->refresh(), 'failed')))->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(70_675_000, $generation->price);
        $this->assertNull($generation->error_code);
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    public function test_an_event_that_fails_to_apply_is_not_recorded_so_its_retry_is_applied(): void
    {
        Exceptions::fake();
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->accepted(GenerationStatus::Processing)->create();
        $remote = $this->remoteGeneration($generation, 'succeeded');

        $this->postWebhook($this->webhookEvent('generation.succeeded', [...$remote, 'usage' => null], 'evt_retried'))
            ->assertServerError();

        Exceptions::assertReported(ErrorException::class);
        $this->assertSame(0, WebhookEvent::query()->count());
        $this->assertSame(GenerationStatus::Processing, $generation->refresh()->status);
        $this->assertSame(0, CreditTransaction::query()->count());

        $this->postWebhook($this->webhookEvent('generation.succeeded', $remote, 'evt_retried'))->assertNoContent();

        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    public function test_it_verifies_the_raw_body_exactly_as_pixelforge_sent_it(): void
    {
        // Pretty-printed with unescaped slashes, so re-encoding the decoded JSON
        // would give different bytes and a different signature.
        $generation = Generation::factory()->accepted()->create();
        $event = $this->webhookEvent('generation.processing', $this->remoteGeneration($generation, 'processing'));

        $this->postWebhook($event, jsonFlags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)->assertNoContent();

        $this->assertSame(GenerationStatus::Processing, $generation->refresh()->status);
    }

    public function test_it_rejects_a_timestamp_too_far_in_the_future(): void
    {
        $generation = Generation::factory()->accepted()->create();
        $event = $this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded'));

        $this->postWebhook($event, timestamp: time() + 301)->assertStatus(400);

        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_it_returns_500_until_a_signing_secret_is_configured(): void
    {
        config(['services.pixelforge.webhook_secret' => null]);

        $this->postWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => ['object' => []]])
            ->assertStatus(500)
            ->assertJson(['error' => 'Webhooks are not configured.']);

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[TestWith([['id' => null]], 'missing generation id')]
    #[TestWith([['status' => 'cancelled']], 'unknown status')]
    public function test_it_rejects_a_generation_event_it_cannot_read_with_422(array $overrides): void
    {
        $generation = Generation::factory()->accepted()->create();
        $remote = [...$this->remoteGeneration($generation, 'processing'), ...$overrides];

        $this->postWebhook($this->webhookEvent('generation.processing', $remote))->assertUnprocessable();

        $this->assertSame(0, WebhookEvent::query()->count());
        $this->assertSame(GenerationStatus::Queued, $generation->refresh()->status);
    }

    public function test_the_webhook_route_runs_without_session_or_csrf_middleware(): void
    {
        // Pixelforge sends no CSRF token, so the route must stay out of the "web"
        // group. Laravel skips CSRF checks while running tests, so no request in
        // a test can catch a 419 here; checking the route's middleware is the
        // only way to guard it.
        $middleware = Route::getRoutes()->getByName('webhooks.pixelforge')->gatherMiddleware();

        $this->assertNotContains('web', $middleware);
    }
}
