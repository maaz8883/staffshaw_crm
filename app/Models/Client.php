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
        'website',
        'customer_group',
        'company_name',
        'brand_id',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'currency',
        'language',
        'billing_address',
        'shipping_address',
        'notes',
        'is_active',
        'team_id',
        'created_by',
        'trello_card_id',
        'trello_board_id',
        'trello_list_id',
        'trello_board_name',
        'trello_list_name',
        'trello_card_url',
        'trello_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'trello_synced_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function primaryContact(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
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

    /** Total invoiced amount (non-void) for this client (sale-linked + standalone). */
    public function totalInvoicedAmount(): float
    {
        $saleIds = $this->sales()->pluck('id');

        return (float) Invoice::query()
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->where(function ($q) use ($saleIds) {
                $q->where('client_id', $this->id);
                if ($saleIds->isNotEmpty()) {
                    $q->orWhereIn('sale_id', $saleIds);
                }
            })
            ->sum('amount');
    }

    /** Count of non-void invoices for this client (sale-linked + standalone). */
    public function invoiceCount(): int
    {
        $saleIds = $this->sales()->pluck('id');

        return Invoice::query()
            ->where('status', '!=', Invoice::STATUS_VOID)
            ->where(function ($q) use ($saleIds) {
                $q->where('client_id', $this->id);
                if ($saleIds->isNotEmpty()) {
                    $q->orWhereIn('sale_id', $saleIds);
                }
            })
            ->count();
    }
}
