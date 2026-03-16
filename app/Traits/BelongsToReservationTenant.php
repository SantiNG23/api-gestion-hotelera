<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Reservation;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait BelongsToReservationTenant
{
    abstract public function reservation();

    public static function bootBelongsToReservationTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->whereHas('reservation', function (Builder $query) use ($tenantId): void {
                $query->withoutGlobalScope('tenant')->where('tenant_id', $tenantId);
            });
        });

        static::saving(function (object $model): void {
            $reservationTenantId = Reservation::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($model->reservation_id)
                ->value('tenant_id');

            if ($reservationTenantId === null) {
                throw ValidationException::withMessages([
                    'reservation_id' => ['La reserva asociada debe pertenecer a un tenant valido.'],
                ]);
            }

            $contextTenantId = app(TenantContext::class)->id();

            if ($contextTenantId !== null && (int) $reservationTenantId !== $contextTenantId) {
                throw ValidationException::withMessages([
                    'reservation_id' => ['La reserva asociada no pertenece al tenant activo.'],
                ]);
            }
        });
    }
}
