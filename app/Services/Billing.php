<?php

namespace App\Services;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Pixelforge\CostEstimate;
use App\Services\Pixelforge\Pricing;
use App\Support\Money;
use InvalidArgumentException;

/**
 * How money is worked out: what Pixelforge charges us for a generation, and
 * what we charge the user for it (Pixelforge's cost plus our markup).
 *
 * Every amount is an integer of nanodollars, see {@see Money}.
 */
final readonly class Billing
{
    public const array TOKEN_TYPES = [
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
    ];

    public function __construct(private int $markupPercent) {}

    /**
     * What Pixelforge bills for these tokens at these rates.
     *
     * Rates are dollars per 1M tokens, so we sum tokens x rate (in nanodollars)
     * and divide by a million once at the end. With Pixelforge's rates the
     * division is always exact; were it ever not, we'd round up.
     *
     * @param  array<string, int>  $tokens
     * @param  array<string, string>  $rates
     */
    public function providerCost(array $tokens, array $rates): int
    {
        $total = 0;

        foreach (self::TOKEN_TYPES as $type) {
            $rate = $rates[$type] ?? throw new InvalidArgumentException("Missing the rate for [{$type}].");

            $total += ($tokens[$type] ?? 0) * Money::fromDecimal($rate);
        }

        return intdiv($total + 999_999, 1_000_000);
    }

    /**
     * What we charge the user for something that cost us $providerCost:
     * the cost plus our markup, rounded up to the next nanodollar.
     */
    public function priceFor(int $providerCost): int
    {
        return intdiv($providerCost * (100 + $this->markupPercent) + 99, 100);
    }

    /**
     * The price range of a generation before it runs. The maximum assumes the
     * most text and reasoning tokens possible, so the user is never charged more.
     */
    public function estimate(Pricing $pricing, int $promptCharacters, ImageSize $size, ImageQuality $quality, int $referenceImages): CostEstimate
    {
        $range = $pricing->tokenRange($promptCharacters, $size, $quality, $referenceImages);

        return new CostEstimate(
            minTokens: $range['min'],
            maxTokens: $range['max'],
            minPrice: $this->priceFor($this->providerCost($range['min'], $pricing->rates)),
            maxPrice: $this->priceFor($this->providerCost($range['max'], $pricing->rates)),
        );
    }
}
