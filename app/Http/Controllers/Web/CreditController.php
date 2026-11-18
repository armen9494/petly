<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\CreditTransactionResource;
use App\Services\CreditLedger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    /**
     * Quick picks on the Credits page, in whole dollars. Any amount between
     * MIN_DOLLARS and MAX_DOLLARS can be bought.
     */
    public const array PACKS = [5, 10, 25, 50];

    public const int MIN_DOLLARS = 1;

    public const int MAX_DOLLARS = 1000;

    public function index(Request $request): Response
    {
        return Inertia::render('web/Credits', [
            'packs' => self::PACKS,
            'limits' => ['min' => self::MIN_DOLLARS, 'max' => self::MAX_DOLLARS],
            'transactions' => CreditTransactionResource::collection(
                $request->user()->creditTransactions()->latest('id')->paginate(25),
            ),
        ]);
    }

    /**
     * A simulated purchase of any amount in range, in dollars and cents: no
     * payment is taken.
     */
    public function store(Request $request, CreditLedger $ledger): RedirectResponse
    {
        $range = sprintf('You can buy between $%s and $%s at a time.', number_format(self::MIN_DOLLARS), number_format(self::MAX_DOLLARS));

        $validated = $request->validate([
            // The regex keeps the amount exact and parseable by Money::fromDecimal().
            'dollars' => ['bail', 'required', 'regex:/^\d{1,4}(\.\d{1,2})?$/', 'numeric', 'min:'.self::MIN_DOLLARS, 'max:'.self::MAX_DOLLARS],
        ], [
            'dollars.required' => 'Choose or enter an amount.',
            'dollars.regex' => 'Enter a dollar amount with up to two decimal places, like 20 or 12.50.',
            'dollars.min' => $range,
            'dollars.max' => $range,
        ]);

        $amount = Money::fromDecimal((string) $validated['dollars']);

        $ledger->purchase($request->user(), $amount);

        Inertia::flash('success', 'Added '.Money::format($amount, 2).' of credits to your account.');

        return back();
    }
}
