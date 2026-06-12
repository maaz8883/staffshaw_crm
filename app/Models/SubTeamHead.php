<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubTeamHead extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'title',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SubTeamHeadTarget::class);
    }

    /**
     * Get all users (members) under this sub-team head
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'sub_team_head_id');
    }
}
