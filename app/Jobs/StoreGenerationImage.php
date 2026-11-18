<?php

namespace App\Jobs;

use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Keep our own copy of a succeeded generation's image.
 *
 * Pixelforge's image links stop working 24 hours after an image is made, so
 * without a copy the history would lose every image after a day. Until the
 * copy is saved, the history shows Pixelforge's link.
 */
class StoreGenerationImage implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public int $timeout = 60;

    public function __construct(public Generation $generation) {}

    public function handle(): void
    {
        $generation = $this->generation->fresh();

        if ($generation === null || $generation->image_path !== null || $generation->image_url === null || $generation->imageHasExpired()) {
            return;
        }

        $response = Http::connectTimeout(5)->timeout(30)->get($generation->image_url)->throw();

        if (! str_starts_with((string) $response->header('Content-Type'), 'image/png')) {
            throw new RuntimeException("Pixelforge's image for generation {$generation->id} isn't a PNG.");
        }

        $path = "generations/{$generation->user_id}/{$generation->uuid}.png";

        if (! Storage::put($path, $response->body())) {
            throw new RuntimeException("Couldn't save the image for generation {$generation->id}.");
        }

        Generation::query()->whereKey($generation->id)->update(['image_path' => $path]);
    }
}
