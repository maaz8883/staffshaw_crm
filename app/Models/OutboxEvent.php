<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'aggregate_type',
        'aggregate_id',
        'context',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'aggregate_id' => 'integer',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
