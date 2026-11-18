<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Jobs\SubmitGenerationToPixelforge;
use App\Models\CreditTransaction;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SyncPendingGenerationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_polls_generations_whose_webhooks_are_overdue(): void
    {
        Queue::fake([StoreGenerationImage::class]);
        $stale = Generation::factory()->accepted(GenerationStatus::Processing)->create(['updated_at' => now()->subMinutes(5)]);
        $fresh = Generation::factory()->accepted(GenerationStatus::Processing)->create();

        Http::fake(["*/v1/generations/{$stale->pixelforge_id}" => Http::response($this->remoteGeneration($stale, 'succeeded'))]);

        $this->artisan('pixelforge:sync')->assertSuccessful();

        $this->assertSame(GenerationStatus::Succeeded, $stale->refresh()->status);
        $this->assertSame(GenerationStatus::Processing, $fresh->refresh()->status);
        $this->assertSame(1, CreditTransaction::query()->count());
        Http::assertSentCount(1);
        Queue::assertPushed(StoreGenerationImage::class, fn (StoreGenerationImage $job) => $job->generation->is($stale));
    }

    public function test_it_ignores_finished_generations(): void
    {
        Generation::factory()->succeeded()->create(['updated_at' => now()->subHour()]);

        Http::fake();

        $this->artisan('pixelforge:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_it_stops_polling_once_pixelforge_rate_limits_it(): void
    {
        $oldest = Generation::factory()->accepted(GenerationStatus::Processing)->create(['updated_at' => now()->subMinutes(10)]);
        $next = Generation::factory()->accepted(GenerationStatus::Processing)->create(['updated_at' => now()->subMinutes(5)]);

        Http::fake([
            "*/v1/generations/{$oldest->pixelforge_id}" => Http::response(
                ['error' => ['type' => 'rate_limit_error', 'code' => 'rate_limit_exceeded', 'message' => 'Too many requests.']],
                429,
                ['Retry-After' => '30'],
            ),
            "*/v1/generations/{$next->pixelforge_id}" => Http::response($this->remoteGeneration($next, 'processing')),
        ]);

        $this->artisan('pixelforge:sync')->assertSuccessful();

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), $next->pixelforge_id));
    }

    public function test_one_failed_poll_does_not_stop_the_others(): void
    {
        $oldest = Generation::factory()->accepted(GenerationStatus::Processing)->create(['updated_at' => now()->subMinutes(10)]);
        $next = Generation::factory()->accepted(GenerationStatus::Processing)->create(['updated_at' => now()->subMinutes(5)]);

        Http::fake([
            "*/v1/generations/{$oldest->pixelforge_id}" => Http::response(
                ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.']],
                503,
            ),
            "*/v1/generations/{$next->pixelforge_id}" => Http::response($this->remoteGeneration($next, 'failed')),
        ]);

        $this->artisan('pixelforge:sync')->assertSuccessful();

        $this->assertSame(GenerationStatus::Processing, $oldest->refresh()->status);
        $this->assertSame(GenerationStatus::Failed, $next->refresh()->status);
    }

    public function test_it_requeues_a_submission_no_job_has_touched_for_five_minutes(): void
    {
        Queue::fake([SubmitGenerationToPixelforge::class]);
        $lost = Generation::factory()->create(['updated_at' => now()->subMinutes(5)]);

        $this->artisan('pixelforge:sync')->expectsOutputToContain('Re-queued 1 stuck generation(s).')->assertSuccessful();

        Queue::assertPushed(SubmitGenerationToPixelforge::class, fn (SubmitGenerationToPixelforge $job) => $job->generation->is($lost));
    }

    public function test_it_leaves_a_recent_submission_to_its_own_job(): void
    {
        Queue::fake([SubmitGenerationToPixelforge::class]);
        Generation::factory()->create(['updated_at' => now()->subMinutes(4)]);

        $this->artisan('pixelforge:sync')->assertSuccessful();

        Queue::assertNotPushed(SubmitGenerationToPixelforge::class);
    }

    public function test_a_requeued_submission_waits_five_more_minutes_before_it_is_requeued_again(): void
    {
        Queue::fake([SubmitGenerationToPixelforge::class]);
        Generation::factory()->create(['updated_at' => now()->subMinutes(5)]);

        $this->artisan('pixelforge:sync')->assertSuccessful();
        $this->travel(4)->minutes();
        $this->artisan('pixelforge:sync')->assertSuccessful();

        Queue::assertPushedTimes(SubmitGenerationToPixelforge::class, 1);
    }

    public function test_a_queue_outage_is_reported_and_polling_carries_on(): void
    {
        Exceptions::fake();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Connection refused [tcp://127.0.0.1:6379]'));
        Generation::factory()->count(2)->create(['updated_at' => now()->subMinutes(10)]);
        $overdue = Generation::factory()->accepted(GenerationStatus::Processing)->create(['updated_at' => now()->subMinutes(5)]);
        Http::fake(["*/v1/generations/{$overdue->pixelforge_id}" => Http::response($this->remoteGeneration($overdue, 'processing'))]);

        $this->artisan('pixelforge:sync')
            ->expectsOutputToContain('Connection refused')
            ->assertSuccessful();

        Exceptions::assertReported(RuntimeException::class);
        Http::assertSentCount(1);
    }
}
