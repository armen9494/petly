<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;

/**
 * What users were charged, what Pixelforge charged us, and the profit, per UTC
 * day and per user, reconciled against Pixelforge's own usage report.
 *
 * Like Pixelforge, a generation counts on the UTC day it completed.
 */
final readonly class SpendReport
{
    public function __construct(private PixelforgeClient $pixelforge) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ours = $this->aggregate($from, $to, ['DATE(completed_at)'], ['DATE(completed_at) as day'])->keyBy('day');
        [$theirs, $usageError] = $this->pixelforgeUsage($from, $to);

        $days = collect(CarbonPeriod::create($from, $to))
            ->reverse()
            ->map(function ($date) use ($ours, $theirs): array {
                $day = $date->format('Y-m-d');

                return $this->row($ours->get($day)) + [
                    'date' => $day,
                    'pixelforge' => $theirs?->get($day),
                ];
            })
            ->values();

        $byUser = $this->aggregate($from, $to, ['user_id'], ['user_id']);
        $users = User::query()->whereIn('id', $byUser->pluck('user_id'))->get(['id', 'name', 'email'])->keyBy('id');

        return [
            'days' => $days,
            'users' => $byUser
                ->map(fn (object $row): array => $this->row($row) + ['user' => $users->get($row->user_id)?->only('id', 'name', 'email')])
                ->sortByDesc('charged_nanos')
                ->values(),
            'days_by_user' => $this->aggregate($from, $to, ['DATE(completed_at)', 'user_id'], ['DATE(completed_at) as day', 'user_id'])
                ->map(fn (object $row): array => $this->row($row) + ['date' => $row->day, 'user' => $users->get($row->user_id)?->only('id', 'name', 'email')])
                ->sortByDesc('date')
                ->values(),
            'total' => $this->totals($from, $to) + [
                'pixelforge' => $theirs === null ? null : [
                    'generations' => $theirs->sum('generations'),
                    'cost' => Money::toDecimal($theirs->sum('cost_nanos')),
                    'cost_nanos' => $theirs->sum('cost_nanos'),
                ],
            ],
            'usage_error' => $usageError,
        ];
    }

    /**
     * The range's totals from our records alone. Unlike build(), this doesn't
     * ask Pixelforge, so it's cheap and can't fail on their side. A missing
     * bound leaves that end of the range open; leave both out for all time.
     *
     * @return array{generations: int, charged: string, charged_nanos: int, provider_cost: string, provider_cost_nanos: int, profit: string}
     */
    public function totals(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        return $this->row($this->aggregate($from, $to)->first());
    }

    /**
     * Sum succeeded generations completed in the range, grouped as asked.
     *
     * @param  list<string>  $groups  SQL expressions to group by.
     * @param  list<string>  $columns  SQL expressions to select alongside the sums.
     * @return Collection<int, object>
     */
    private function aggregate(?CarbonImmutable $from, ?CarbonImmutable $to, array $groups = [], array $columns = []): Collection
    {
        return Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->when($from, fn (Builder $query) => $query->where('completed_at', '>=', $from->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('completed_at', '<=', $to->endOfDay()))
            ->selectRaw(implode(', ', [
                ...$columns,
                'COUNT(*) as generations',
                'SUM(price) as charged',
                'SUM(provider_cost) as provider_cost',
            ]))
            ->when($groups !== [], fn (Builder $query) => $query->groupByRaw(implode(', ', $groups)))
            ->toBase()
            ->get();
    }

    /**
     * @return array{generations: int, charged: string, charged_nanos: int, provider_cost: string, provider_cost_nanos: int, profit: string}
     */
    private function row(?object $row): array
    {
        $charged = (int) ($row->charged ?? 0);
        $providerCost = (int) ($row->provider_cost ?? 0);

        return [
            'generations' => (int) ($row->generations ?? 0),
            'charged' => Money::toDecimal($charged),
            'charged_nanos' => $charged,
            'provider_cost' => Money::toDecimal($providerCost),
            'provider_cost_nanos' => $providerCost,
            'profit' => Money::toDecimal($charged - $providerCost),
        ];
    }

    /**
     * Pixelforge's usage report for the range, keyed by day.
     *
     * @return array{0: Collection<string, array<string, mixed>>|null, 1: string|null}
     */
    private function pixelforgeUsage(CarbonImmutable $from, CarbonImmutable $to): array
    {
        try {
            $usage = $this->pixelforge->usage($from, $to);
        } catch (PixelforgeException|ConnectionException $exception) {
            return [null, "Couldn't load Pixelforge's usage report: {$exception->getMessage()}"];
        }

        $days = collect($usage['data'])->mapWithKeys(fn (array $day): array => [$day['date'] => [
            'generations' => $day['generations'],
            'cost' => $day['cost_usd'],
            'cost_nanos' => Money::fromDecimal($day['cost_usd']),
        ]]);

        return [$days, null];
    }
}
