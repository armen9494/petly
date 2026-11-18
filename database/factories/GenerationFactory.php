<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * A medium 1024x1024 image with a 31-character prompt, which costs the
     * user between $0.0581625 and $0.0863125.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => GenerationStatus::Pending,
            'prompt' => 'A lighthouse on a cliff at dawn',
            'size' => ImageSize::Square,
            'quality' => ImageQuality::Medium,
            'reference_images' => [],
            'estimated_min_price' => 58_162_500,
            'estimated_max_price' => 86_312_500,
        ];
    }

    public function accepted(GenerationStatus $status = GenerationStatus::Queued): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ])->afterMaking(function (Generation $generation): void {
            $generation->pixelforge_id = 'gen_'.Str::upper(Str::random(26));
            $generation->rates = [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ];
        });
    }

    /**
     * A succeeded generation that cost us $providerCost nanodollars, charged at cost + 25%.
     */
    public function succeeded(int $providerCost = 50_000_000, ?string $completedAt = null): static
    {
        return $this->accepted(GenerationStatus::Succeeded)->afterMaking(function (Generation $generation) use ($providerCost, $completedAt): void {
            $generation->provider_cost = $providerCost;
            $generation->price = intdiv($providerCost * 125 + 99, 100);
            $generation->completed_at = $completedAt ?? now();
        });
    }
}
