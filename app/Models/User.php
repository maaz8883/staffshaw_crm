<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ACCOUNT_ACTIVE = 'active';

    public const ACCOUNT_PENDING = 'pending';

    public const ACCOUNT_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'team_id',
        'company_id',
        'account_status',
        'rejection_note',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function userTargets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserTarget::class);
    }

    public function sales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Approved sign-in users only (excludes pending / rejected self-registrations). */
    public function scopeAccountActive(Builder $query): Builder
    {
        return $query->where('account_status', self::ACCOUNT_ACTIVE);
    }

    public function hasRole(string|array $roles): bool
    {
        $currentRole = $this->role?->name;

        if ($currentRole === null) {
            return false;
        }

        return in_array($currentRole, (array) $roles, true);
    }

    public function isAccountActive(): bool
    {
        return $this->account_status === self::ACCOUNT_ACTIVE;
    }

    public function isPendingApproval(): bool
    {
        return $this->account_status === self::ACCOUNT_PENDING;
    }

    public function isAccountRejected(): bool
    {
        return $this->account_status === self::ACCOUNT_REJECTED;
    }
}
