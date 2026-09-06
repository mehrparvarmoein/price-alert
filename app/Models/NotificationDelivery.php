<?php

namespace App\Models;

use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'idempotency_key',
        'status',
        'sending_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationDeliveryStatus::class,
            'sending_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(PriceAlert::class);
    }
}