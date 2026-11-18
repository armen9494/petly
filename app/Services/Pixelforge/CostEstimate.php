<?php

namespace App\Services\Pixelforge;

use App\Support\Money;

/**
 * What a generation will cost the user, before it runs. Prices are nanodollars.
 */
final readonly class CostEstimate
{
    /**
     * @param  array<string, int>  $minTokens
     * @param  array<string, int>  $maxTokens
     */
    public function __construct(
        public array $minTokens,
        public array $maxTokens,
        public int $minPrice,
        public int $maxPrice,
    ) {}

    /**
     * @return array{min_price: string, max_price: string, tokens: array{min: array<string, int>, max: array<string, int>}}
     */
    public function toArray(): array
    {
        return [
            'min_price' => Money::toDecimal($this->minPrice),
            'max_price' => Money::toDecimal($this->maxPrice),
            'tokens' => [
                'min' => $this->minTokens,
                'max' => $this->maxTokens,
            ],
        ];
    }
}
