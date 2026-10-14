<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrelloBoardWebhook extends Model
{
    protected $fillable = [
        'team_id',
        'board_id',
        'webhook_id',
        'secret',
        'active',
        'last_event_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_event_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
