<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    public const TYPE_NOTE = 'note';

    public const TYPE_FOLLOW_UP = 'follow_up';

    public const TYPE_CONTACT = 'contact';

    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'body',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
