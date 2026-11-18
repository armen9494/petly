<?php

namespace App\Http\Resources\Shared;

use App\Models\CreditTransaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => Money::toDecimal($this->amount),
            'description' => $this->description,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
