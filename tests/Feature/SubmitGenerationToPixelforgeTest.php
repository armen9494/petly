<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Jobs\SubmitGenerationToPixelforge;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Services\Pixelforge\GenerationSynchronizer;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SubmitGenerationToPixelforgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_submits_with_an_idempotency_key_and_our_reference(): void
    {
        $generation = Generation::factory()->create(['reference_images' => ['https://example.com/style.png']]);
        $remote = $this->remoteGeneration($generation, 'queued');
        $this->fakePixelforge(['*/v1/generations' => Http::response($remote, 202)]);

        $this->runJob($generation);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.pixelforge.example/v1/generations'
            && $request->hasHeader('Idempotency-Key', $generation->uuid)
            && $request->hasHeader('Authorization', 'Bearer pf_test_key')
            && $request['metadata'][Generation::METADATA_KEY] === $generation->uuid
            && $request['reference_images'] === ['https://example.com/style.png']);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertSame($remote['id'], $generation->pixelforge_id);
        $this->assertSame('40.00', $generation->rates['output_tokens']);
    }

    public function test_it_does_not_send_an_empty_reference_image_list(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response($this->remoteGeneration($generation, 'queued'), 202)]);

        $this->runJob($generation);

        Http::assertSent(fn (Request $request) => ! array_key_exists('reference_images', $request->data()));
    }

    public function test_a_create_response_does_not_undo_a_webhook_that_arrived_first(): void
    {
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->create();
        $remote = $this->remoteGeneration($generation, 'succeeded');
        app(GenerationSynchronizer::class)->apply($generation, $remote);

        $this->fakePixelforge(['*/v1/generations' => Http::response([...$remote, 'status' => 'queued'], 202)]);
        $this->runJob($generation);

        $this->assertSame(GenerationStatus::Succeeded, $generation->refresh()->status);
        $this->assertSame(1, CreditTransaction::query()->count());
        Queue::assertPushedTimes(StoreGenerationImage::class, 1);
    }

    public function test_overloads_are_retried_after_retry_after(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Pixelforge is temporarily overloaded.']],
            503,
            ['Retry-After' => '2'],
        )]);

        $job = $this->runJob($generation);

        $job->assertReleased(delay: 2);
        $generation->refresh();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame('Pixelforge is overloaded. Retrying shortly.', $generation->status_detail);
    }

    public function test_concurrency_limits_wait_for_a_free_slot(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Too many generations.']],
            429,
            ['Retry-After' => '5'],
        )]);

        $this->runJob($generation)->assertReleased(delay: 5);

        $this->assertStringContainsString('free slot', $generation->refresh()->status_detail);
    }

    public function test_retries_back_off_beyond_retry_after(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.']],
            503,
            ['Retry-After' => '2'],
        )]);

        $this->runJob($generation, attempts: 5)->assertReleased(delay: 32);
    }

    public function test_it_gives_up_after_the_last_attempt_without_charging(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.']],
            503,
            ['Retry-After' => '2'],
        )]);

        $this->runJob($generation, attempts: 10)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('overloaded', $generation->error_code);
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_a_blocked_prompt_fails_immediately_and_is_free(): void
    {
        $generation = Generation::factory()->create(['prompt' => 'Something [blocked]']);
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'The prompt was rejected.']],
            400,
        )]);

        $this->runJob($generation)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertStringContainsString('content policy', $generation->error_message);
    }

    public function test_the_daily_quota_is_not_retried(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'rate_limit_error', 'code' => 'daily_limit_exceeded', 'message' => 'Daily quota used up.']],
            429,
        )]);

        $this->runJob($generation)->assertNotReleased();

        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
    }

    public function test_network_errors_are_retried_with_the_same_idempotency_key(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->runJob($generation)->assertReleased(delay: 2);

        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
    }

    public function test_pixelforge_overrides_a_failure_we_recorded_before_it_confirmed_acceptance(): void
    {
        // Every attempt timed out, so we gave up; but Pixelforge had accepted it after all.
        Queue::fake([StoreGenerationImage::class]);
        $generation = Generation::factory()->create();
        app(GenerationSynchronizer::class)->failBeforeAcceptance($generation, 'unreachable', "We couldn't reach Pixelforge.");

        $this->postWebhook($this->webhookEvent('generation.succeeded', $this->remoteGeneration($generation, 'succeeded')))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertNull($generation->error_code);
        $this->assertSame(1, CreditTransaction::query()->count());
        Queue::assertPushed(StoreGenerationImage::class, fn (StoreGenerationImage $job) => $job->generation->is($generation));
    }

    public function test_it_skips_generations_that_were_already_submitted(): void
    {
        $generation = Generation::factory()->accepted()->create();

        $this->runJob($generation);

        Http::assertNothingSent();
    }

    public function test_rate_limits_are_retried_after_retry_after(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'rate_limit_error', 'code' => 'rate_limit_exceeded', 'message' => 'Too many requests.']],
            429,
            ['Retry-After' => '10'],
        )]);

        $this->runJob($generation)->assertReleased(delay: 10);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame('Pixelforge is rate limiting us. Retrying shortly.', $generation->status_detail);
    }

    public function test_an_unexpected_server_error_without_an_error_body_is_retried(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response('<html>Bad gateway</html>', 502)]);

        $this->runJob($generation)->assertReleased(delay: 2);

        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
    }

    public function test_network_errors_on_the_last_attempt_fail_the_generation_for_free(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::failedConnection('Connection timed out')]);

        $this->runJob($generation, attempts: 10)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('unreachable', $generation->error_code);
        $this->assertSame("We couldn't reach Pixelforge. You were not charged; please try again.", $generation->error_message);
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_a_rejected_api_key_fails_without_retrying_and_alerts_operators(): void
    {
        Log::spy();
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => ['type' => 'authentication_error', 'code' => 'invalid_api_key', 'message' => 'Invalid API key.']],
            401,
        )]);

        $this->runJob($generation)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('invalid_api_key', $generation->error_code);
        $this->assertSame('Image generation is not configured correctly. We have been notified.', $generation->error_message);
        Log::shouldHaveReceived('critical')->with('Pixelforge rejected our API key.', ['generation' => $generation->id])->once();
    }

    public function test_a_missing_api_key_fails_the_generation_without_calling_pixelforge(): void
    {
        config(['services.pixelforge.api_key' => null]);
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::response($this->remoteGeneration($generation, 'queued'), 202)]);

        $this->runJob($generation)->assertNotReleased();

        Http::assertNothingSent();
        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('invalid_api_key', $generation->error_code);
    }

    public function test_pixelforge_validation_errors_are_shown_to_the_user(): void
    {
        $generation = Generation::factory()->create(['reference_images' => ['https://example.com/style.png']]);
        $this->fakePixelforge(['*/v1/generations' => Http::response(
            ['error' => [
                'type' => 'invalid_request_error',
                'code' => 'validation_failed',
                'message' => 'The reference_images.0 field must point to an image.',
                'param' => 'reference_images.0',
            ]],
            422,
        )]);

        $this->runJob($generation)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('Pixelforge rejected the request: The reference_images.0 field must point to an image.', $generation->error_message);
    }

    public function test_a_job_that_crashes_on_its_last_attempt_fails_the_generation_for_free(): void
    {
        $generation = Generation::factory()->create();

        (new SubmitGenerationToPixelforge($generation))->failed(new RuntimeException('The worker ran out of memory.'));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('submission_failed', $generation->error_code);
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_a_job_that_crashes_after_pixelforge_accepted_the_generation_leaves_it_alone(): void
    {
        $generation = Generation::factory()->accepted()->create();

        (new SubmitGenerationToPixelforge($generation))->failed(new RuntimeException('The worker ran out of memory.'));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertNull($generation->error_code);
    }

    private function runJob(Generation $generation, int $attempts = 1): SubmitGenerationToPixelforge
    {
        $job = (new SubmitGenerationToPixelforge($generation))->withFakeQueueInteractions();
        $job->job->attempts = $attempts;

        $job->handle(app(PixelforgeClient::class), app(GenerationSynchronizer::class));

        return $job;
    }
}
