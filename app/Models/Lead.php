<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    public const SOURCES = ['website', 'upwork', 'referral', 'social_media', 'email', 'cold_call', 'other'];

    public const STATUSES = ['new', 'contacted', 'proposal', 'won', 'lost'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'brand_id',
        'source',
        'status',
        'assigned_to',
        'team_id',
        'notes',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
