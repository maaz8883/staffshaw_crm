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
    public const TYPE_SALE_APPROVED = 'sale_approved';
    public const TYPE_SALE_REJECTED = 'sale_rejected';
    public const TYPE_SALE_REFUNDED = 'sale_refunded';
    public const TYPE_SALE_REFUND_REVERTED = 'sale_refund_reverted';
    public const TYPE_TEAM_CREATED = 'team_created';
    public const TYPE_TEAM_UPDATED = 'team_updated';
    public const TYPE_TEAM_DELETED = 'team_deleted';
    public const TYPE_USER_CREATED = 'user_created';
    public const TYPE_USER_UPDATED = 'user_updated';
    public const TYPE_USER_DELETED = 'user_deleted';
    public const TYPE_TEAM_TARGET_SET = 'team_target_set';
    public const TYPE_USER_TARGET_SET = 'user_target_set';
    public const TYPE_PPC_ADDED    = 'ppc_added';
    public const TYPE_PPC_DELETED  = 'ppc_deleted';
    public const TYPE_SMTP_UPDATED = 'smtp_updated';
    public const TYPE_OTP_UPDATED  = 'otp_updated';
    public const TYPE_BACKUP_CREATED = 'backup_created';
    public const TYPE_BACKUP_DELETED = 'backup_deleted';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_LOGIN          => 'success',
            self::TYPE_LOGOUT         => 'secondary',
            self::TYPE_SALE_CREATED   => 'primary',
            self::TYPE_SALE_UPDATED   => 'warning',
            self::TYPE_SALE_DELETED   => 'danger',
            self::TYPE_SALE_APPROVED  => 'success',
            self::TYPE_SALE_REJECTED  => 'danger',
            self::TYPE_SALE_REFUNDED  => 'warning',
            self::TYPE_SALE_REFUND_REVERTED => 'info',
            self::TYPE_TEAM_CREATED   => 'success',
            self::TYPE_TEAM_UPDATED   => 'warning',
            self::TYPE_TEAM_DELETED   => 'danger',
            self::TYPE_USER_CREATED   => 'success',
            self::TYPE_USER_UPDATED   => 'warning',
            self::TYPE_USER_DELETED   => 'danger',
            self::TYPE_TEAM_TARGET_SET => 'primary',
            self::TYPE_USER_TARGET_SET => 'info',
            self::TYPE_PPC_ADDED      => 'info',
            self::TYPE_PPC_DELETED    => 'danger',
            self::TYPE_SMTP_UPDATED   => 'info',
            self::TYPE_OTP_UPDATED    => 'info',
            self::TYPE_BACKUP_CREATED => 'success',
            self::TYPE_BACKUP_DELETED => 'danger',
            default                   => 'light',
        };
    }
}
