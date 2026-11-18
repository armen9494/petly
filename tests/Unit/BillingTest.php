<?php

namespace Tests\Unit;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Billing;
use App\Services\Pixelforge\Pricing;
use App\Support\Money;
use Tests\TestCase;

class BillingTest extends TestCase
{
    private Billing $billing;

    private Pricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->billing = new Billing(markupPercent: 25);
        $this->pricing = Pricing::fromApi($this->pricingPayload());
    }

    public function test_provider_cost_matches_the_worked_example_in_the_docs(): void
    {
        // High 1536x1024, a 400-character prompt, 2 reference images, most tokens possible.
        $cost = $this->billing->providerCost([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ], $this->pricing->rates);

        $this->assertSame('0.373875000', Money::toDecimal($cost));
    }

    public function test_provider_cost_matches_the_usage_example_in_the_docs(): void
    {
        $cost = $this->billing->providerCost([
            'input_text_tokens' => 41,
            'input_image_tokens' => 0,
            'output_tokens' => 3300,
            'reasoning_tokens' => 2950,
        ], $this->pricing->rates);

        $this->assertSame('0.169080000', Money::toDecimal($cost));
    }

    public function test_the_user_pays_cost_plus_25_percent_exactly(): void
    {
        $this->assertSame(467_343_750, $this->billing->priceFor(373_875_000));
        $this->assertSame(0, $this->billing->priceFor(0));
    }

    public function test_markup_rounds_up_to_the_next_nanodollar(): void
    {
        $this->assertSame(2, $this->billing->priceFor(1));
        $this->assertSame(4, $this->billing->priceFor(3));
    }

    public function test_the_estimate_spans_the_cheapest_and_most_expensive_outcome(): void
    {
        $estimate = $this->billing->estimate($this->pricing, 400, ImageSize::Landscape, ImageQuality::High, 2);

        $this->assertSame(['input_text_tokens' => 85, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 1000], $estimate->minTokens);
        $this->assertSame(['input_text_tokens' => 115, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000], $estimate->maxTokens);
        $this->assertSame($this->billing->priceFor(373_875_000), $estimate->maxPrice);
        $this->assertSame($this->billing->priceFor(286_225_000), $estimate->minPrice);
    }

    public function test_the_text_token_range_rounds_outwards(): void
    {
        // 31 characters is ceil(31 / 4) = 8 tokens, so 6.8 to 9.2: at least 6, at most 10.
        $range = $this->pricing->tokenRange(31, ImageSize::Square, ImageQuality::Low, 0);

        $this->assertSame(6, $range['min']['input_text_tokens']);
        $this->assertSame(10, $range['max']['input_text_tokens']);
    }
}
