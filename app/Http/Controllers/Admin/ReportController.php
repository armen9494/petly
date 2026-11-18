<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SpendReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Pixelforge's usage report covers at most 92 days at a time.
     */
    private const int MAX_DAYS = 92;

    public function show(Request $request, SpendReport $report): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'], 'UTC') : CarbonImmutable::today('UTC');
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'], 'UTC') : $to->subDays(29);

        if ($from->greaterThan($to) || $from->diffInDays($to) >= self::MAX_DAYS) {
            throw ValidationException::withMessages([
                'from' => 'Choose a range of 1 to '.self::MAX_DAYS.' days.',
            ]);
        }

        return Inertia::render('admin/Report', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'markupPercent' => config('services.pixelforge.markup_percent'),
            'report' => $report->build($from, $to),
        ]);
    }
}
