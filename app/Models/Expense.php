<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const CATEGORIES = ['Hosting', 'Marketing', 'Production Tools', 'Other'];

    protected $fillable = [
        'category',
        'amount',
        'tax_amount',
        'expense_date',
        'note',
        'client_id',
        'brand_id',
        'team_id',
        'is_billable',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'expense_date' => 'date',
        'is_billable' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
