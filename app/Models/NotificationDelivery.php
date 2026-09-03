<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    public function priceAlert(): BelongsTo
    {
        return $this->belongsTo(PriceAlert::class);
    }
}