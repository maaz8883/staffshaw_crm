<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmTask extends Model
{
    protected $table = 'tasks';

    public const STATUSES = [
        'brief_pending',
        'not_started',
        'in_progress',
        'revision',
        'submitted_to_pm',
        'sent_to_client',
        'completed',
        'on_hold',
    ];

    public const STATUS_LABELS = [
        'brief_pending' => 'Brief Pending',
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'revision' => 'Revision',
        'submitted_to_pm' => 'Submitted To PM',
        'sent_to_client' => 'Sent to client',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'subject',
        'description',
        'hourly_rate',
        'start_date',
        'due_date',
        'priority',
        'status',
        'is_public',
        'is_billable',
        'related_type',
        'related_id',
        'repeat_every',
        'tags',
        'brand_id',
        'team_id',
        'created_by',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'start_date' => 'date',
        'due_date' => 'date',
        'is_public' => 'boolean',
        'is_billable' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')
            ->withPivot('relation')
            ->wherePivot('relation', 'assignee')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')
            ->withPivot('relation')
            ->wherePivot('relation', 'follower')
            ->withTimestamps();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->status !== 'completed'
            && $this->due_date->isPast();
    }
}
