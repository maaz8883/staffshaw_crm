<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'type', 'description', 'ip_address', 'country', 'city', 'user_agent', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // Activity type constants
    public const TYPE_LOGIN        = 'login';
    public const TYPE_LOGOUT       = 'logout';
    public const TYPE_SALE_CREATED = 'sale_created';
    public const TYPE_SALE_UPDATED = 'sale_updated';
    public const TYPE_SALE_DELETED = 'sale_deleted';
    public const TYPE_PPC_ADDED    = 'ppc_added';
    public const TYPE_PPC_DELETED  = 'ppc_deleted';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_LOGIN        => 'success',
            self::TYPE_LOGOUT       => 'secondary',
            self::TYPE_SALE_CREATED => 'primary',
            self::TYPE_SALE_UPDATED => 'warning',
            self::TYPE_SALE_DELETED => 'danger',
            self::TYPE_PPC_ADDED    => 'info',
            self::TYPE_PPC_DELETED  => 'danger',
            default                 => 'light',
        };
    }
}
