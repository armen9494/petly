<?php

namespace App\Services\Pixelforge;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * Pixelforge's published rates and token rules, as returned by GET /v1/pricing.
 */
final readonly class Pricing
{
    /**
     * @param  array<string, string>  $rates  US dollars per 1M tokens, keyed by token type.
     * @param  array<string, array<string, int>>  $outputTokens  Exact output tokens by quality, then size.
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  Reasoning token range by quality.
     */
    public function __construct(
        public array $rates,
        public int $charactersPerTextToken,
        public int $textTokenVarianceBasisPoints,
        public int $tokensPerReferenceImage,
        public array $outputTokens,
        public array $reasoningTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The GET /v1/pricing response body.
     */
    public static function fromApi(array $data): self
    {
        return new self(
            rates: $data['rates'],
            charactersPerTextToken: (int) $data['tokens']['characters_per_text_token'],
            textTokenVarianceBasisPoints: (int) round($data['tokens']['text_token_variance'] * 10_000),
            tokensPerReferenceImage: (int) $data['tokens']['per_reference_image'],
            outputTokens: $data['tokens']['output'],
            reasoningTokens: $data['tokens']['reasoning'],
        );
    }

    /**
     * The fewest and the most tokens of each type a generation can be billed for.
     *
     * Text tokens are estimated as ceil(characters / 4) and the real count is
     * within the published variance of that; reasoning tokens fall within a
     * range per quality. Everything else is exact.
     *
     * @return array{min: array<string, int>, max: array<string, int>}
     */
    public function tokenRange(int $promptCharacters, ImageSize $size, ImageQuality $quality, int $referenceImages): array
    {
        $estimatedTextTokens = intdiv($promptCharacters + $this->charactersPerTextToken - 1, $this->charactersPerTextToken);
        $imageTokens = $referenceImages * $this->tokensPerReferenceImage;
        $outputTokens = $this->outputTokens[$quality->value][$size->value];
        $reasoning = $this->reasoningTokens[$quality->value];

        return [
            'min' => [
                'input_text_tokens' => intdiv($estimatedTextTokens * (10_000 - $this->textTokenVarianceBasisPoints), 10_000),
                'input_image_tokens' => $imageTokens,
                'output_tokens' => $outputTokens,
                'reasoning_tokens' => $reasoning['min'],
            ],
            'max' => [
                'input_text_tokens' => intdiv($estimatedTextTokens * (10_000 + $this->textTokenVarianceBasisPoints) + 9_999, 10_000),
                'input_image_tokens' => $imageTokens,
                'output_tokens' => $outputTokens,
                'reasoning_tokens' => $reasoning['max'],
            ],
        ];
    }
}
