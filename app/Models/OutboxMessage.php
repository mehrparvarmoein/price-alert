<?php

namespace App\Models;

use Database\Factories\OutboxMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    /** @use HasFactory<OutboxMessageFactory> */
    use HasFactory;

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
