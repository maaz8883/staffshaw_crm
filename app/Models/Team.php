<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'is_production',
    ];

    protected $casts = [
        'is_production' => 'boolean',
    ];

    protected static function booted(): void
    {
        // A sub-team always follows its main team (sub-teams of Production are production too)
        static::saving(function (Team $team) {
            if ($team->parent_team_id) {
                $team->is_production = (bool) Team::query()->whereKey($team->parent_team_id)->value('is_production');
            }
        });
        static::saved(function (Team $team) {
            if (! $team->parent_team_id && $team->wasChanged('is_production')) {
                Team::query()->where('parent_team_id', $team->id)->update(['is_production' => $team->is_production]);
            }
        });
    }

    /** Sales teams only (Staffshaw, Nextbyte…): production teams have no sales, targets or leads. */
    public function scopeSales($query)
    {
        return $query->where('teams.is_production', false);
    }

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

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /** Project Managers with an approved Team_Membership on this team. */
    public function approvedProjectManagers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_memberships', 'team_id', 'user_id')
            ->wherePivot('status', TeamMembership::STATUS_APPROVED);
    }

    /** Pending Project Manager join requests for this team. */
    public function pendingTeamMemberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class)->where('status', TeamMembership::STATUS_PENDING);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** Brands this team may select in sales, invoices, clients, etc. */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_team')->withTimestamps();
    }

    public function trelloIntegration(): HasOne
    {
        return $this->hasOne(TeamTrelloIntegration::class);
    }
}
