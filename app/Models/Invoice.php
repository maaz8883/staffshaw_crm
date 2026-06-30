<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'sale_id',
        'invoice_number',
        'issued_at',
        'amount',
        'currency',
        'client_name',
        'client_email',
        'client_phone',
        'title',
        'notes',
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
        'amount'        => 'decimal:2',
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

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_VOID   => 'Void',
            default             => 'Issued',
        };
    }
}
