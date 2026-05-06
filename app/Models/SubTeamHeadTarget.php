<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubTeamHeadTarget extends Model
{
    protected $fillable = [
        'sub_team_head_id',
        'team_id',
        'month',
        'year',
        'target_amount',
        'notes',
    ];

    public function subTeamHead(): BelongsTo
    {
        return $this->belongsTo(SubTeamHead::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
