<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Money columns (estimated_min_price, estimated_max_price, provider_cost,
 * price) are integers of nanodollars.
 */
#[Fillable([
    'status', 'status_detail', 'prompt', 'size', 'quality', 'reference_images',
    'estimated_min_price', 'estimated_max_price',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    /**
     * The key we send to Pixelforge in each generation's metadata, so a webhook
     * can be matched to its generation even before the create request returns.
     */
    public const string METADATA_KEY = 'generation_uuid';

    /**
     * The uuid is generated on insert; the primary key stays an auto-incrementing id.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'estimated_min_price' => 'integer',
            'estimated_max_price' => 'integer',
            'provider_cost' => 'integer',
            'price' => 'integer',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<CreditTransaction, $this>
     */
    public function charge(): HasOne
    {
        return $this->hasOne(CreditTransaction::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inFlight(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::inFlight());
    }

    /**
     * Find the generation a Pixelforge generation object belongs to.
     *
     * @param  array<string, mixed>  $remote
     */
    public static function findForPixelforge(array $remote): ?self
    {
        $uuid = $remote['metadata'][self::METADATA_KEY] ?? null;

        return self::query()->where('pixelforge_id', $remote['id'])->first()
            ?? ($uuid !== null ? self::query()->where('uuid', $uuid)->first() : null);
    }

    /**
     * Whether we gave up before Pixelforge confirmed it had accepted the
     * generation. Pixelforge may still have created it (say, the request timed
     * out after it was accepted), so its word overrides this failure.
     */
    public function failedBeforeAcceptance(): bool
    {
        return $this->status === GenerationStatus::Failed && $this->pixelforge_id === null;
    }

    /**
     * Whether an update from Pixelforge to $incoming should be applied.
     * Generations only move forward and never leave a final status, so
     * duplicated or out-of-order events can't undo anything.
     */
    public function acceptsUpdateTo(GenerationStatus $incoming): bool
    {
        if ($this->failedBeforeAcceptance()) {
            return true;
        }

        return $incoming->rank() > $this->status->rank();
    }

    public function imageHasExpired(): bool
    {
        return $this->image_url_expires_at?->isPast() ?? false;
    }

    /**
     * The body of the POST /v1/generations request for this generation.
     *
     * @return array<string, mixed>
     */
    public function toPixelforgeRequest(): array
    {
        return array_filter([
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->reference_images ?: null,
            'metadata' => [
                self::METADATA_KEY => $this->uuid,
                'user_id' => (string) $this->user_id,
            ],
        ], fn (mixed $value) => $value !== null);
    }
}
