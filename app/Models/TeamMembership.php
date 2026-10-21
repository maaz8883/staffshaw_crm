<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMembership extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'team_id',
        'status',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Project Managers belong to teams through memberships, not users.team_id. When an admin
     * puts a PM in a team (Users → create / edit), approve that membership right away, and drop
     * the membership of the team they were moved out of (teams they joined themselves stay).
     */
    public static function syncProjectManagerTeam(User $user, ?int $oldTeamId, ?int $decidedBy): void
    {
        if (! $user->hasRole(Role::PROJECT_MANAGER)) {
            return;
        }
        $newTeamId = $user->team_id ? (int) $user->team_id : null;

        if ($oldTeamId && $oldTeamId !== $newTeamId) {
            self::query()->where('user_id', $user->id)->where('team_id', $oldTeamId)->delete();
        }
        if ($newTeamId) {
            self::query()->updateOrCreate(
                ['user_id' => $user->id, 'team_id' => $newTeamId],
                ['status' => self::STATUS_APPROVED, 'decided_by' => $decidedBy, 'decided_at' => now()]
            );
        }
    }
}
