<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'long_description',
        'qty',
        'rate',
        'tax_rate',
        'amount',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'rate' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
