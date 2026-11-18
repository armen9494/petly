<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Jobs\StoreGenerationImage;
use App\Models\Generation;
use App\Services\Billing;
use App\Services\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Brings our generations in line with Pixelforge.
 *
 * Pixelforge tells us about a generation three ways: the create response, its
 * webhooks and polling. All three funnel into {@see apply()}, which is safe to
 * call any number of times and in any order.
 */
final readonly class GenerationSynchronizer
{
    public function __construct(
        private Billing $billing,
        private CreditLedger $ledger,
    ) {}

    /**
     * Apply a Pixelforge generation object to our generation.
     *
     * The row is locked while we decide, so concurrent webhooks and polls are
     * applied one at a time. A generation only moves forward (see
     * {@see Generation::acceptsUpdateTo()}), and it is charged in the same
     * transaction that marks it succeeded, so the charge happens exactly once.
     * Once that commits, its image is copied before Pixelforge's link expires.
     *
     * @param  array<string, mixed>  $remote
     */
    public function apply(Generation $generation, array $remote): Generation
    {
        return DB::transaction(function () use ($generation, $remote): Generation {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->id);
            $incoming = GenerationStatus::from($remote['status']);
            $shouldUpdate = $generation->acceptsUpdateTo($incoming);

            $generation->pixelforge_id ??= $remote['id'];
            $generation->rates ??= $remote['rates'];
            $generation->submitted_at ??= $this->time($remote['created_at'] ?? null);

            if ($shouldUpdate) {
                $generation->status = $incoming;
                $generation->status_detail = null;
                $generation->started_at = $this->time($remote['started_at'] ?? null);
                $generation->completed_at = $this->time($remote['completed_at'] ?? null);
                $generation->error_code = $remote['error']['code'] ?? null;
                $generation->error_message = $remote['error']['message'] ?? null;

                if ($incoming === GenerationStatus::Succeeded) {
                    $this->recordUsage($generation, $remote);
                }
            }

            $generation->save();

            if ($shouldUpdate && $incoming === GenerationStatus::Succeeded) {
                $this->ledger->charge($generation);

                StoreGenerationImage::dispatch($generation);
            }

            return $generation;
        });
    }

    /**
     * Note that we're waiting to retry submitting a generation.
     */
    public function markWaiting(Generation $generation, string $detail): void
    {
        Generation::query()
            ->whereKey($generation->id)
            ->where('status', GenerationStatus::Pending)
            ->update(['status_detail' => $detail]);
    }

    /**
     * Fail a generation Pixelforge never accepted. Nothing is charged.
     */
    public function failBeforeAcceptance(Generation $generation, string $errorCode, string $message): void
    {
        Generation::query()
            ->whereKey($generation->id)
            ->where('status', GenerationStatus::Pending)
            ->update([
                'status' => GenerationStatus::Failed,
                'status_detail' => null,
                'error_code' => $errorCode,
                'error_message' => $message,
                'completed_at' => now(),
            ]);
    }

    /**
     * Record what a succeeded generation used and what it costs, at the rates
     * Pixelforge fixed for it when it was created.
     *
     * @param  array<string, mixed>  $remote
     */
    private function recordUsage(Generation $generation, array $remote): void
    {
        $usage = $remote['usage'];

        $generation->rates = $remote['rates'];
        $generation->input_text_tokens = $usage['input_text_tokens'];
        $generation->input_image_tokens = $usage['input_image_tokens'];
        $generation->output_tokens = $usage['output_tokens'];
        $generation->reasoning_tokens = $usage['reasoning_tokens'];
        $generation->provider_cost = $this->billing->providerCost($usage, $remote['rates']);
        $generation->price = $this->billing->priceFor($generation->provider_cost);
        $generation->image_url = $remote['image_url'];
        $generation->image_url_expires_at = $this->time($remote['image_url_expires_at'] ?? null);
    }

    private function time(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->utc();
    }
}
