<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\GenerationSynchronizer;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Send a pending generation to Pixelforge.
 *
 * Every attempt uses the generation's uuid as the Idempotency-Key, so retrying
 * after a timeout or an overload never creates (or bills) a second generation.
 * Rate, concurrency and overload rejections are retried after Retry-After,
 * backing off further each time, up to $tries attempts. Anything else
 * Pixelforge rejects fails the generation for free.
 */
class SubmitGenerationToPixelforge implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $timeout = 30;

    public function __construct(public Generation $generation) {}

    public function handle(PixelforgeClient $pixelforge, GenerationSynchronizer $synchronizer): void
    {
        $generation = $this->generation->fresh();

        if ($generation?->status !== GenerationStatus::Pending) {
            return;
        }

        try {
            $remote = $pixelforge->createGeneration($generation->toPixelforgeRequest(), $generation->uuid);
        } catch (PixelforgeException $exception) {
            if ($exception->isTransient() && $this->canRetry()) {
                $this->retryLater($synchronizer, $exception->waitingMessage(), $exception->retryAfter);

                return;
            }

            if ($exception->errorCode === 'invalid_api_key') {
                Log::critical('Pixelforge rejected our API key.', ['generation' => $generation->id]);
            }

            $synchronizer->failBeforeAcceptance($generation, $exception->errorCode, $exception->userMessage());

            return;
        } catch (ConnectionException) {
            if ($this->canRetry()) {
                $this->retryLater($synchronizer, "Couldn't reach Pixelforge. Retrying shortly.");

                return;
            }

            $synchronizer->failBeforeAcceptance($generation, 'unreachable', "We couldn't reach Pixelforge. You were not charged; please try again.");

            return;
        }

        $synchronizer->apply($generation, $remote);
    }

    /**
     * Called when the job crashes on its last attempt.
     */
    public function failed(?Throwable $exception): void
    {
        app(GenerationSynchronizer::class)->failBeforeAcceptance(
            $this->generation,
            'submission_failed',
            'Something went wrong sending this to Pixelforge. You were not charged; please try again.',
        );
    }

    private function canRetry(): bool
    {
        return $this->attempts() < $this->tries;
    }

    /**
     * Wait at least as long as Pixelforge asked, and longer with each attempt:
     * 2s, 4s, 8s, ... capped at a minute.
     */
    private function retryLater(GenerationSynchronizer $synchronizer, string $reason, ?int $retryAfter = null): void
    {
        $delay = max($retryAfter ?? 0, min(60, 2 ** $this->attempts()));

        $synchronizer->markWaiting($this->generation, $reason);

        $this->release($delay);
    }
}
