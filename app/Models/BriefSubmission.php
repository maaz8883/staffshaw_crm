<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefSubmission extends Model
{
    public const STATUS_PENDING   = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'sale_id',
        'brief_type',
        'form_path',
        'data',
        'attachments',
        'status',
        'submitted_at',
        'client_name',
        'client_email',
        'client_ip',
    ];

    protected $casts = [
        'data'         => 'array',
        'attachments'  => 'array',
        'submitted_at' => 'datetime',
    ];

    /** @return BelongsTo<Sale, BriefSubmission> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
