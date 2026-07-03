<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'address',
        'notes',
        'team_id',
        'created_by',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Total amount across all sales for this client (excludes drafts). */
    public function totalSaleAmount(): float
    {
        return (float) $this->sales()->where('is_draft', false)->sum('amount');
    }

    /** Total amount received across all sales for this client. */
    public function totalReceivedAmount(): float
    {
        return (float) $this->sales()->where('is_draft', false)->sum('received_amount');
    }

    /** Total amount still owed across all sales for this client. */
    public function totalRemainingAmount(): float
    {
        return max(0, $this->totalSaleAmount() - $this->totalReceivedAmount());
    }

    /** Total invoiced amount (non-void) across all sales for this client. */
    public function totalInvoicedAmount(): float
    {
        return (float) Invoice::query()
            ->whereIn('sale_id', $this->sales()->pluck('id'))
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->sum('amount');
    }

    /** Count of non-void invoices across all sales for this client. */
    public function invoiceCount(): int
    {
        return Invoice::query()
            ->whereIn('sale_id', $this->sales()->pluck('id'))
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->count();
    }
}
