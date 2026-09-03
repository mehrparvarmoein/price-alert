<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'alert_id',
        'idempotency_key',
        'status',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(PriceAlert::class);
    }
}