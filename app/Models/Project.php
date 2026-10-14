<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUSES = ['not_started', 'in_progress', 'on_hold', 'cancelled', 'finished'];

    public const STATUS_LABELS = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
        'finished' => 'Finished',
    ];

    public const BILLING_TYPES = [
        'fixed_rate' => 'Fixed Rate',
        'project_hours' => 'Project Hours',
        'task_hours' => 'Task Hours',
    ];

    protected $fillable = [
        'name',
        'client_id',
        'brand_id',
        'team_id',
        'status',
        'billing_type',
        'total_rate',
        'estimated_hours',
        'progress',
        'progress_from_tasks',
        'start_date',
        'deadline',
        'tags',
        'description',
        'created_by',
    ];

    protected $casts = [
        'total_rate' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'progress_from_tasks' => 'boolean',
        'start_date' => 'date',
        'deadline' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'related_id')->where('related_type', 'project');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function refreshProgress(): void
    {
        if (! $this->progress_from_tasks) {
            return;
        }

        $total = $this->tasks()->count();
        $done = $this->tasks()->where('status', 'completed')->count();
        $this->progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        $this->save();
    }
}
