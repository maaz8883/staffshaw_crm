<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Sale extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Sale $sale) {
            DB::transaction(function () use ($sale) {
                // Receipts created on approve
                $sale->invoices()->each(function (Invoice $invoice) {
                    $invoice->items()->delete();
                    $invoice->payments()->delete();
                    $invoice->delete();
                });

                // Client briefs tied to this sale
                $sale->briefSubmissions()->delete();

                // In-app bells that link to this sale
                DatabaseNotification::query()
                    ->where(function ($q) use ($sale) {
                        $q->where('data->sale_id', $sale->id)
                            ->orWhere('data->sale_id', (string) $sale->id);
                    })
                    ->delete();
            });

            // Uploaded brief files: brief-uploads/{saleId}/...
            $uploadDir = 'brief-uploads/'.$sale->id;
            if (Storage::disk('public')->exists($uploadDir)) {
                Storage::disk('public')->deleteDirectory($uploadDir);
            }
        });
    }

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
        'client_id',
        'lead_id',
        'client_name',
        'client_email',
        'client_phone',
        'amount',
        'received_amount',
        'sale_date',
        'user_id',
        'sales_agent_name',
        'account_manager_id',
        'account_manager_name',
        'project_manager_id',
        'project_manager_name',
        'team_id',
        'company_id',
        'brand_id',
        'status',
        'status_before_refund',
        'sale_type',
        'is_refunded',
        'refunded_at',
        'refunded_by',
        'notes',
        'merchant',
        'is_ppc',
        'approval_status',
        'is_draft',
        'approval_note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'sale_date'    => 'date',
        'amount'          => 'decimal:2',
        'received_amount' => 'decimal:2',
        'user_id'      => 'integer',
        'account_manager_id' => 'integer',
        'project_manager_id' => 'integer',
        'team_id'      => 'integer',
        'company_id'   => 'integer',
        'brand_id'     => 'integer',
        'client_id'    => 'integer',
        'lead_id'      => 'integer',
        'approved_at'  => 'datetime',
        'refunded_at'  => 'datetime',
        'is_refunded'  => 'boolean',
        'is_ppc'       => 'boolean',
        'is_draft'     => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function briefSubmissions(): HasMany
    {
        return $this->hasMany(BriefSubmission::class);
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

        if ($this->isPendingApproval() && $this->status === 'pending') {
            return 'Awaiting Approval';
        }

        if ($this->approval_status === self::APPROVAL_REJECTED && $this->status === 'pending') {
            return 'Needs Revision';
        }

        return ucfirst($this->status);
    }

    public function isEditableByOwner(): bool
    {
        if ($this->is_refunded || $this->status === self::STATUS_REFUNDED) {
            return false;
        }

        return $this->isPendingApproval()
            || $this->approval_status === self::APPROVAL_REJECTED;
    }

    public function remainingAmount(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->received_amount), 2);
    }

    /** Live CRM user, or imported historical name when that person is no longer in the system. */
    public function agentDisplayName(): ?string
    {
        return $this->user?->name ?: ($this->sales_agent_name ?: null);
    }

    public function accountManagerDisplayName(): ?string
    {
        return $this->accountManager?->name ?: ($this->account_manager_name ?: null);
    }

    public function projectManagerDisplayName(): ?string
    {
        return $this->projectManager?->name ?: ($this->project_manager_name ?: null);
    }

    /** True when we only have a text name (no linked user account). */
    public function agentIsHistoricalOnly(): bool
    {
        return $this->user_id === null && filled($this->sales_agent_name);
    }
}
