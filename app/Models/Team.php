<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'parent_team_id',
        'team_head_id',
        'sub_team_head_id',
        'name',
        'description',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function teamHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_head_id');
    }

    public function subTeamHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sub_team_head_id');
    }

    public function parentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    public function subTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'parent_team_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TeamTarget::class);
    }

    public function userTargets(): HasMany
    {
        return $this->hasMany(UserTarget::class);
    }

    public function subTeamHeads(): HasMany
    {
        return $this->hasMany(SubTeamHead::class);
    }
}
