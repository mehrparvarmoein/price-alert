<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PriceAlert extends Model
{
    protected function casts(): array
    {
        return [
            'target_price' => 'integer',
            'processing_at' => 'datetime',
            'triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificationDelivery(): HasOne
    {
        return $this->hasOne(NotificationDelivery::class);
    }
}