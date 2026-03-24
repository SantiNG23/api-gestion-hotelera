<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingInvitation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $table = 'onboarding_invitations';

    protected $fillable = [
        'email',
        'token_hash',
        'expires_at',
        'consumed_at',
        'revoked_at',
        'tenant_name_prefill',
        'tenant_slug_prefill',
        'created_by',
        'meta',
    ];

    protected $hidden = [
        'token_hash',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusAttribute(): string
    {
        if ($this->isRevoked()) {
            return self::STATUS_REVOKED;
        }

        if ($this->isConsumed()) {
            return self::STATUS_CONSUMED;
        }

        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_PENDING;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
