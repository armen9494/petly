<?php

namespace App\Http\Resources\Admin;

use App\Models\Generation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation request as admins see it: what was asked for, what it was
 * expected to cost and what it actually cost. It never includes the image,
 * which stays private to its owner. Money is exact nine-decimal strings.
 *
 * The error code and Pixelforge id are for tracing a failure: the code says
 * whose side it failed on, and the id is what Pixelforge support asks for.
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
            'pixelforge_id' => $this->pixelforge_id,
            'status' => $this->status,
            'status_detail' => $this->status_detail,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'size_label' => $this->size->label(),
            'quality' => $this->quality,
            'reference_images_count' => count($this->reference_images),
            'estimated_min_price' => Money::toDecimal($this->estimated_min_price),
            'estimated_max_price' => Money::toDecimal($this->estimated_max_price),
            'price' => $this->price === null ? null : Money::toDecimal($this->price),
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user->only('id', 'name', 'email')),
        ];
    }
}
