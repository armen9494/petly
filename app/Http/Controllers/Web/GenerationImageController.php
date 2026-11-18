<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationImageController extends Controller
{
    /**
     * Serve our copy of a generation's image (see {@see StoreGenerationImage}).
     *
     * If the copy has gone missing, fall back to Pixelforge's link while it
     * still works.
     */
    public function show(Generation $generation): StreamedResponse|RedirectResponse
    {
        if ($generation->image_path === null || Storage::missing($generation->image_path)) {
            abort_if($generation->image_url === null || $generation->imageHasExpired(), 404);

            return redirect()->away($generation->image_url);
        }

        return Storage::response($generation->image_path, "{$generation->uuid}.png", [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
