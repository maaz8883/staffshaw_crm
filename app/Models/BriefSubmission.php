<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BriefSubmission extends Model
{
    public const STATUS_PENDING   = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    protected $connection = 'orbit_brand';

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
        'sale_id'      => 'integer',
        'data'         => 'array',
        'attachments'  => 'array',
        'submitted_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeForSale(Builder $query, int $saleId): Builder
    {
        return $query->where('sale_id', $saleId);
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
