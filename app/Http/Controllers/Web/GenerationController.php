<?php

namespace App\Http\Controllers\Web;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreGenerationRequest;
use App\Jobs\SubmitGenerationToPixelforge;
use App\Models\Generation;
use App\Models\User;
use App\Services\Billing;
use App\Services\CreditLedger;
use App\Services\Pixelforge\CostEstimate;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class GenerationController extends Controller
{
    /**
     * Save the generation and queue it for Pixelforge.
     *
     * The user must be able to afford its maximum possible price, on top of
     * what their other unfinished generations might cost.
     */
    public function store(StoreGenerationRequest $request, PixelforgeClient $pixelforge, Billing $billing, CreditLedger $ledger): RedirectResponse
    {
        $prompt = $request->string('prompt')->toString();
        $size = $request->enum('size', ImageSize::class);
        $quality = $request->enum('quality', ImageQuality::class);
        $referenceImages = $request->input('reference_images', []);

        try {
            $estimate = $billing->estimate($pixelforge->pricing(), mb_strlen($prompt), $size, $quality, count($referenceImages));
        } catch (PixelforgeException|ConnectionException) {
            throw ValidationException::withMessages([
                'pricing' => "We couldn't load Pixelforge's prices just now. Please try again in a moment.",
            ]);
        }

        $generation = DB::transaction(function () use ($request, $ledger, $estimate, $prompt, $size, $quality, $referenceImages): Generation {
            // Lock the user so two quick submissions can't spend the same credits.
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            $this->ensureAffordable($estimate, $ledger->available($user));

            return $user->generations()->create([
                'status' => GenerationStatus::Pending,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'estimated_min_price' => $estimate->minPrice,
                'estimated_max_price' => $estimate->maxPrice,
            ]);
        });

        try {
            SubmitGenerationToPixelforge::dispatch($generation);
        } catch (Throwable $exception) {
            // It's saved but not queued; pixelforge:sync queues it within a few
            // minutes. Don't show an error page: the user might submit again
            // and pay for two images.
            report($exception);
            $generation->update(['status_detail' => 'Delayed on our side. It will be sent to Pixelforge within a few minutes.']);
            Inertia::flash('success', 'Your image is saved and will start in a few minutes. You will only be charged once it succeeds.');

            return to_route('studio');
        }

        Inertia::flash('success', 'Your image is queued. You will only be charged once it succeeds.');

        return to_route('studio');
    }

    private function ensureAffordable(CostEstimate $estimate, int $available): void
    {
        if ($estimate->maxPrice <= $available) {
            return;
        }

        throw ValidationException::withMessages([
            'credits' => sprintf(
                'This image can cost up to %s, but you have %s available. Buy credits to continue.',
                Money::format($estimate->maxPrice),
                Money::format(max(0, $available)),
            ),
        ]);
    }
}
