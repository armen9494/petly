<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Pixelforge webhook event we have processed, kept to ignore redeliveries.
 */
#[Fillable(['event_id', 'type', 'pixelforge_generation_id', 'payload', 'received_at'])]
class WebhookEvent extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }
}
