<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    public const SOURCES = ['website', 'upwork', 'referral', 'social_media', 'email', 'cold_call', 'other'];

    /**
     * Keep the pipeline short so agents are not confused.
     * Legacy values (new, won, low_funds, not_connected) are remapped in migration.
     */
    public const STATUSES = [
        'new_lead',
        'contacted',
        'follow_up',
        'proposal',
        'customer',
        'lost',
    ];

    public const STATUS_LABELS = [
        'new_lead' => 'New Lead',
        'contacted' => 'Contacted',
        'follow_up' => 'Follow Up',
        'proposal' => 'Proposal',
        'customer' => 'Customer',
        'lost' => 'Lost',
    ];

    protected $fillable = [
        'name',
        'company',
        'position',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'lead_value',
        'tags',
        'last_contact_at',
        'follow_up_at',
        'is_public',
        'client_id',
        'brand_id',
        'source',
        'status',
        'assigned_to',
        'team_id',
        'notes',
    ];

    protected $casts = [
        'lead_value' => 'decimal:2',
        'last_contact_at' => 'date',
        'follow_up_at' => 'date',
        'is_public' => 'boolean',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }

    public function isFollowUpOverdue(): bool
    {
        return $this->follow_up_at !== null
            && $this->follow_up_at->isPast()
            && ! in_array($this->status, ['customer', 'lost'], true);
    }
}
