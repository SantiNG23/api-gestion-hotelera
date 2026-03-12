<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class UserSetting extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
        });

    }

    protected $table = 'user_settings';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'locale',
        'timezone',
        'marketing_emails',
        'transactional_emails',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tenant_id' => 'integer',
        'marketing_emails' => 'boolean',
        'transactional_emails' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function save(array $options = [])
    {
        $this->guardTenantConsistency();

        return parent::save($options);
    }

    private function guardTenantConsistency(): void
    {
        $userTenantId = User::query()
            ->whereKey($this->user_id)
            ->value('tenant_id');

        if ($userTenantId === null) {
            throw ValidationException::withMessages([
                'user_id' => ['El usuario asociado debe pertenecer a un tenant valido.'],
            ]);
        }

        $this->tenant_id = (int) $userTenantId;

        $contextTenantId = app(TenantContext::class)->id();

        if ($contextTenantId !== null && (int) $this->tenant_id !== $contextTenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => ['La configuracion del usuario no pertenece al tenant activo.'],
            ]);
        }
    }
}
