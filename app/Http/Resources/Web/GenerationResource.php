<?php

namespace App\Http\Resources\Web;

use App\Models\Generation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as its owner sees it. Money is exact nine-decimal strings.
 *
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_detail' => $this->status_detail,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'size_label' => $this->size->label(),
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            'estimated_min_price' => Money::toDecimal($this->estimated_min_price),
            'estimated_max_price' => Money::toDecimal($this->estimated_max_price),
            'price' => $this->price === null ? null : Money::toDecimal($this->price),
            'tokens' => $this->output_tokens === null ? null : [
                'input_text' => $this->input_text_tokens,
                'input_image' => $this->input_image_tokens,
                'output' => $this->output_tokens,
                'reasoning' => $this->reasoning_tokens,
            ],
            'image_url' => match (true) {
                $this->image_path !== null => route('generations.image', $this->resource),
                $this->imageHasExpired() => null,
                default => $this->image_url,
            },
            'image_expired' => $this->image_path === null && $this->image_url !== null && $this->imageHasExpired(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
