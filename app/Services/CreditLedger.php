<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Users' credits, kept as an append-only ledger of signed nanodollar amounts.
 *
 * A user's balance is the sum of their ledger rows. Generations that haven't
 * finished hold back their maximum possible price, so a user can't start
 * more than they can pay for.
 */
final class CreditLedger
{
    public function balance(User $user): int
    {
        return (int) $user->creditTransactions()->sum('amount');
    }

    /**
     * Credits reserved for generations that haven't finished yet.
     */
    public function held(User $user): int
    {
        return (int) $user->generations()->inFlight()->sum('estimated_max_price');
    }

    public function available(User $user): int
    {
        return $this->balance($user) - $this->held($user);
    }

    /**
     * The user's credits as exact nine-decimal dollar strings, for display.
     *
     * @return array{balance: string, held: string, available: string}
     */
    public function summary(User $user): array
    {
        $balance = $this->balance($user);
        $held = $this->held($user);

        return [
            'balance' => Money::toDecimal($balance),
            'held' => Money::toDecimal($held),
            'available' => Money::toDecimal($balance - $held),
        ];
    }

    /**
     * Add credits to a user's account. Purchases are simulated: no payment is taken.
     */
    public function purchase(User $user, int $amount): CreditTransaction
    {
        return $user->creditTransactions()->create([
            'type' => CreditTransactionType::Purchase,
            'amount' => $amount,
            'description' => 'Bought '.Money::format($amount, 2).' of credits',
        ]);
    }

    /**
     * Charge a succeeded generation's price to its user.
     *
     * Idempotent: the ledger has a unique index on generation_id, and an
     * existing charge is returned instead of creating a second one.
     */
    public function charge(Generation $generation): CreditTransaction
    {
        return CreditTransaction::query()->firstOrCreate(
            ['generation_id' => $generation->id],
            [
                'user_id' => $generation->user_id,
                'type' => CreditTransactionType::Charge,
                'amount' => -$generation->price,
                'description' => 'Image: '.Str::limit($generation->prompt, 80),
            ],
        );
    }
}
