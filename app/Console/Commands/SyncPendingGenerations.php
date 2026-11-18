<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationToPixelforge;
use App\Models\Generation;
use App\Services\Pixelforge\GenerationSynchronizer;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Keeps generations moving when something along the way was lost. Runs every
 * minute from the scheduler.
 *
 * - A generation can be saved but never queued for Pixelforge, say if the
 *   queue was down at that moment. Those are queued again.
 * - Webhook delivery isn't guaranteed, so we poll Pixelforge for generations
 *   we haven't heard about for a while.
 */
#[Signature('pixelforge:sync
    {--limit=20 : The most generations to re-queue, and to poll, in one run}
    {--stuck-for=5 : Re-queue pending generations untouched for this many minutes}
    {--quiet-for=2 : Poll generations without news for this many minutes}')]
#[Description('Re-queue lost submissions and poll Pixelforge for generations whose webhooks are overdue')]
class SyncPendingGenerations extends Command
{
    public function handle(PixelforgeClient $pixelforge, GenerationSynchronizer $synchronizer): int
    {
        $this->requeueStuckSubmissions();
        $this->pollOverdueGenerations($pixelforge, $synchronizer);

        return self::SUCCESS;
    }

    /**
     * Queue again the pending generations no job has touched for a while.
     *
     * A live submission job touches its generation at least every minute or
     * so while it retries, so a quiet one has no job. Should the job only be
     * slow, a second one is harmless: both send the same Idempotency-Key, so
     * Pixelforge never creates or bills a second generation.
     */
    private function requeueStuckSubmissions(): void
    {
        $stuck = Generation::query()
            ->where('status', GenerationStatus::Pending)
            ->where('updated_at', '<=', now()->subMinutes((int) $this->option('stuck-for')))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($stuck as $generation) {
            try {
                SubmitGenerationToPixelforge::dispatch($generation);
            } catch (Throwable $exception) {
                // Any failure to queue means the queue is unavailable, so
                // there's no point trying the rest until the next run.
                report($exception);
                $this->components->error("Couldn't re-queue generation {$generation->id}: {$exception->getMessage()}");

                return;
            }

            // Wait another full period before re-queuing it again.
            $generation->touch();
        }

        if ($stuck->isNotEmpty()) {
            $this->components->info("Re-queued {$stuck->count()} stuck generation(s).");
        }
    }

    private function pollOverdueGenerations(PixelforgeClient $pixelforge, GenerationSynchronizer $synchronizer): void
    {
        $generations = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<=', now()->subMinutes((int) $this->option('quiet-for')))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($generations as $generation) {
            try {
                $synchronizer->apply($generation, $pixelforge->generation($generation->pixelforge_id));
            } catch (PixelforgeException|ConnectionException $exception) {
                $this->components->warn("Couldn't poll {$generation->pixelforge_id}: {$exception->getMessage()}");

                if ($exception instanceof PixelforgeException && $exception->status === 429) {
                    break;
                }
            }
        }

        $this->components->info("Polled {$generations->count()} generation(s).");
    }
}
