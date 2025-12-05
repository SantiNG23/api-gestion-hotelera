<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

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
        // Scope global para filtrar por tenant del usuario autenticado
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check() && Auth::user()->tenant_id) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', Auth::user()->tenant_id);
            }
        });

        // Asignar automáticamente el tenant_id al crear
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->tenant_id && !$model->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
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
