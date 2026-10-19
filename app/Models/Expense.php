<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    /** Suggestions in the form (any text is allowed): day-to-day office costs first. */
    public const CATEGORIES = [
        'Food & Refreshments', 'Transport & Fuel', 'Office Supplies', 'Utilities', 'Internet & Phone',
        'Repairs & Maintenance', 'Hosting', 'Marketing', 'Production Tools', 'Other',
    ];

    /** Expenses are recorded in Pakistani rupees (sales / invoices stay in USD). */
    public const CURRENCY = 'Rs';

    /** "Rs 1,500" / "Rs 1,250.50" */
    public static function money(float|int|string|null $amount): string
    {
        $v = round((float) $amount, 2);

        return self::CURRENCY.' '.number_format($v, fmod($v, 1.0) == 0.0 ? 0 : 2);
    }

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
