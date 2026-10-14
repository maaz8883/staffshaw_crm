<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_VOID = 'void';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'sale_id',
        'client_id',
        'invoice_number',
        'issued_at',
        'due_date',
        'amount',
        'discount',
        'adjustment',
        'tax_total',
        'currency',
        'client_name',
        'client_email',
        'client_phone',
        'title',
        'notes',
        'client_note',
        'terms',
        'brand_id',
        'brand_name',
        'agent_name',
        'team_name',
        'company_name',
        'sale_total',
        'sale_received',
        'sale_balance',
        'status',
        'created_by',
    ];

    protected $casts = [
        'issued_at'     => 'date',
        'due_date'      => 'date',
        'amount'        => 'decimal:2',
        'discount'      => 'decimal:2',
        'adjustment'    => 'decimal:2',
        'tax_total'     => 'decimal:2',
        'sale_total'    => 'decimal:2',
        'sale_received' => 'decimal:2',
        'sale_balance'  => 'decimal:2',
        'sale_id'       => 'integer',
        'brand_id'      => 'integer',
        'created_by'    => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function amountPaid(): float
    {
        if ($this->sale_id) {
            return (float) $this->amount;
        }

        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }

        return (float) $this->payments()->sum('amount');
    }

    public function amountDue(): float
    {
        // Sale-linked rows are payment receipts — nothing remains due on the invoice itself.
        if ($this->sale_id) {
            return 0.0;
        }

        return max(0, round((float) $this->amount - $this->amountPaid(), 2));
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isStandalone(): bool
    {
        return $this->sale_id === null;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_VOID) {
            return 'Void';
        }

        if ($this->status === self::STATUS_DRAFT) {
            return 'Draft';
        }

        if ($this->sale_id) {
            return 'Issued';
        }

        $due = $this->amountDue();
        $paid = $this->amountPaid();

        if ($due <= 0) {
            return 'Paid';
        }

        if ($paid > 0) {
            return 'Partially Paid';
        }

        if ($this->due_date && $this->due_date->isPast()) {
            return 'Overdue';
        }

        return 'Unpaid';
    }

    public function statusColor(): string
    {
        return match ($this->statusLabel()) {
            'Paid' => 'success',
            'Partially Paid' => 'warning',
            'Overdue' => 'danger',
            'Draft' => 'secondary',
            'Void' => 'secondary',
            'Unpaid' => 'danger',
            'Issued' => 'success',
            default => 'primary',
        };
    }
}
