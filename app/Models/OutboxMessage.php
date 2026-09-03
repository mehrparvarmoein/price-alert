<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    protected $fillable = [
        'type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }
}
