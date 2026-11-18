<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as admins see them. Money is exact nine-decimal strings.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'is_admin' => $this->is_admin,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
            'generations_count' => $this->whenCounted('generations'),
            // withSum() gives null for a user with no ledger rows, which is a zero balance.
            'balance' => $this->whenHas('credit_transactions_sum_amount', fn (int|string|null $sum) => Money::toDecimal((int) $sum)),
        ];
    }
}
