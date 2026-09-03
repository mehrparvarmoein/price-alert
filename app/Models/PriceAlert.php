<?php

namespace App\Models;

use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Enums\AlertStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PriceAlert extends Model
{
    protected $fillable = [
        'user_id',
        'target_price',
        'direction',
        'status',
        'processing_at',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'integer',
            'direction' => AlertDirection::class,
            'status' => AlertStatus::class,
            'processing_at' => 'immutable_datetime',
            'triggered_at' => 'immutable_datetime',
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