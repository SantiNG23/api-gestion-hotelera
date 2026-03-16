<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Trait para modelos que pertenecen a un tenant
 *
 * Este trait agrega automáticamente el scope global para filtrar por tenant_id
 * y la relación con el modelo Tenant.
 *
 * Solo usar en modelos "raíz": Client, Cabin, Feature, PriceGroup, PriceRange, Reservation
 * NO usar en: ReservationGuest, ReservationPayment (filtran vía Reservation)
 */
trait BelongsToTenant
{
    /**
     * Boot del trait
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::saving(function ($model) {
            self::guardTenantWrite($model);
        });

        static::creating(function ($model) {
            self::guardTenantWrite($model);
        });

        static::updating(function ($model) {
            self::guardTenantWrite($model);
        });
    }

    private static function guardTenantWrite(object $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            throw MissingTenantContextException::forOperation();
        }

        if (! isset($model->tenant_id)) {
            $model->tenant_id = $tenantId;

            return;
        }

        if ((int) $model->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => ['El tenant_id no coincide con el contexto tenant activo.'],
            ]);
        }
    }

    /**
     * Relación con el tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para consultar sin filtro de tenant (uso administrativo)
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
