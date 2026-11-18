<?php

namespace App\Enums;

/**
 * A generation's lifecycle. Pending is ours (saved, not yet accepted by
 * Pixelforge); the rest mirror Pixelforge's statuses.
 */
enum GenerationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * How far along the lifecycle this status is. A generation only ever
     * moves to a status with a higher rank, which is what makes stale,
     * duplicated and out-of-order updates harmless.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }

    /**
     * @return list<self>
     */
    public static function inFlight(): array
    {
        return [self::Pending, self::Queued, self::Processing];
    }
}
