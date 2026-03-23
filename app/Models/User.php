<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_STAFF = 'staff';

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId !== null) {
                if ($user->tenant_id === null) {
                    $user->tenant_id = $tenantId;
                }

                if ((int) $user->tenant_id !== $tenantId) {
                    throw ValidationException::withMessages([
                        'tenant_id' => ['El tenant_id del usuario no coincide con el contexto tenant activo.'],
                    ]);
                }

                return;
            }

            if ($user->tenant_id === null) {
                throw ValidationException::withMessages([
                    'tenant_id' => ['El usuario debe pertenecer a un tenant valido.'],
                ]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Relación con el tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
