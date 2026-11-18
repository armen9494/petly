<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * The headline numbers on the admin overview, for the last 30 UTC days (the
 * spend report's default range) and right now, plus what Pixelforge has
 * charged us all time, in those 30 days and today.
 */
final readonly class AdminOverview
{
    public const int DAYS = 30;

    /**
     * Webhooks usually finish a generation within a minute or two, and
     * pixelforge:sync polls every minute, so one still running after this long
     * has probably been missed by both.
     */
    public const int STUCK_AFTER_MINUTES = 15;

    public function __construct(private SpendReport $spendReport) {}

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     users: array{total: int, new: int, inactive: int},
     *     generations: array{succeeded: int, failed: int, in_flight: int, stuck: int},
     *     spend: array<string, int|string>,
     *     unspent_credits: string,
     *     paid_to_pixelforge: array{
     *         all_time: array{generations: int, provider_cost: string},
     *         last_30_days: array{generations: int, provider_cost: string},
     *         today: array{generations: int, provider_cost: string},
     *     },
     * }
     */
    public function stats(): array
    {
        $to = CarbonImmutable::today('UTC');
        $from = $to->subDays(self::DAYS - 1);
        $spend = $this->spendReport->totals($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'users' => [
                'total' => User::query()->count(),
                'new' => User::query()->where('created_at', '>=', $from)->count(),
                'inactive' => User::query()->where('is_active', false)->count(),
            ],
            'generations' => $this->generations($from),
            'spend' => $spend,
            'unspent_credits' => Money::toDecimal((int) CreditTransaction::query()->sum('amount')),
            'paid_to_pixelforge' => [
                'all_time' => $this->providerCost($this->spendReport->totals()),
                'last_30_days' => $this->providerCost($spend),
                'today' => $this->providerCost($this->spendReport->totals($to, $to)),
            ],
        ];
    }

    /**
     * The part of a spend total that Pixelforge charged us, by our records.
     *
     * @param  array<string, int|string>  $totals  A SpendReport::totals() result.
     * @return array{generations: int, provider_cost: string}
     */
    private function providerCost(array $totals): array
    {
        return Arr::only($totals, ['generations', 'provider_cost']);
    }

    /**
     * How the generations requested since $from turned out, and how many are
     * still running now.
     *
     * @return array{succeeded: int, failed: int, in_flight: int, stuck: int}
     */
    private function generations(CarbonImmutable $from): array
    {
        $finished = Generation::query()
            ->where('created_at', '>=', $from)
            ->whereIn('status', [GenerationStatus::Succeeded, GenerationStatus::Failed])
            ->toBase()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'succeeded' => (int) ($finished[GenerationStatus::Succeeded->value] ?? 0),
            'failed' => (int) ($finished[GenerationStatus::Failed->value] ?? 0),
            'in_flight' => Generation::query()->inFlight()->count(),
            'stuck' => Generation::query()->inFlight()->where('created_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))->count(),
        ];
    }
}
