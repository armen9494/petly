<?php

namespace App\Http\Controllers\Web;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreGenerationRequest;
use App\Services\Billing;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstimateController extends Controller
{
    /**
     * The price range of a generation, priced from Pixelforge's current rates.
     *
     * Only the prompt's length matters, so the form sends the character count
     * rather than the prompt.
     */
    public function show(Request $request, PixelforgeClient $pixelforge, Billing $billing): JsonResponse
    {
        $validated = $request->validate([
            'characters' => ['required', 'integer', 'min:0', 'max:'.StoreGenerationRequest::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['required', 'integer', 'min:0', 'max:'.StoreGenerationRequest::MAX_REFERENCE_IMAGES],
        ]);

        try {
            $pricing = $pixelforge->pricing();
        } catch (PixelforgeException|ConnectionException) {
            return response()->json(['message' => "Pixelforge's prices are unavailable right now."], 503);
        }

        $estimate = $billing->estimate(
            $pricing,
            (int) $validated['characters'],
            ImageSize::from($validated['size']),
            ImageQuality::from($validated['quality']),
            (int) $validated['reference_images'],
        );

        return response()->json($estimate->toArray());
    }
}
