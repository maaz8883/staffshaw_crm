<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasFactory;

    /** User-selectable statuses (create / edit); refunded is set only via refund toggle. */
    public const STATUSES = ['pending', 'completed', 'cancelled'];

    public const STATUS_REFUNDED = 'refunded';

    public const TYPE_FRONT  = 'front';

    public const TYPE_UPSELL = 'upsell';

    /** @var list<string> */
    public const SALE_TYPES = [self::TYPE_FRONT, self::TYPE_UPSELL];

    public const APPROVAL_PENDING  = 'pending_approval';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'title',
        'client_name',
        'amount',
        'sale_date',
        'user_id',
        'team_id',
        'company_id',
        'status',
        'status_before_refund',
        'sale_type',
        'is_refunded',
        'refunded_at',
        'refunded_by',
        'notes',
        'approval_status',
        'approval_note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'sale_date'    => 'date',
        'amount'       => 'decimal:2',
        'approved_at'  => 'datetime',
        'refunded_at'  => 'datetime',
        'is_refunded'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function saleTypeLabel(): string
    {
        return match ($this->sale_type) {
            self::TYPE_UPSELL => 'Upsell',
            default           => 'Front',
        };
    }

    /** All status values stored in DB (charts / filters). */
    public static function allStatuses(): array
    {
        return [...self::STATUSES, self::STATUS_REFUNDED];
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_REFUNDED) {
            return 'Refunded';
        }

        return ucfirst($this->status);
    }
}
