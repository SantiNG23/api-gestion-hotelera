<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio para generar resumen diario de eventos
 *
 * NO extiende Service porque no maneja CRUD de una entidad
 */
class DailySummaryService
{
    /**
     * Genera el resumen diario
     *
     * @param  Carbon|null  $date  Fecha para el resumen (default: hoy)
     * @return array{has_events: bool, check_ins: Collection, check_outs: Collection, expiring_pending: Collection, summary: array}
     */
    public function getDailySummary(?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();

        $checkIns = $this->getCheckInsForDate($date);
        $checkOuts = $this->getCheckOutsForDate($date);
        $expiringPending = $this->getExpiringPendingReservations($date);

        $hasEvents = $checkIns->isNotEmpty()
            || $checkOuts->isNotEmpty()
            || $expiringPending->isNotEmpty();

        return [
            'has_events' => $hasEvents,
            'check_ins' => $checkIns,
            'check_outs' => $checkOuts,
            'expiring_pending' => $expiringPending,
        ];
    }

    /**
     * Obtiene las reservas con check-in para una fecha
     *
     * @return Collection<Reservation>
     */
    public function getCheckInsForDate(Carbon $date): Collection
    {
        return Reservation::whereDate('check_in_date', $date)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->with(['client', 'cabin', 'guests'])
            ->orderBy('cabin_id')
            ->get();
    }

    /**
     * Obtiene las reservas con check-out para una fecha
     *
     * @return Collection<Reservation>
     */
    public function getCheckOutsForDate(Carbon $date): Collection
    {
        return Reservation::whereDate('check_out_date', $date)
            ->whereIn('status', [
                Reservation::STATUS_CHECKED_IN,
            ])
            ->with(['client', 'cabin'])
            ->orderBy('cabin_id')
            ->get();
    }

    /**
     * Obtiene las reservas con pagos pendientes o expiraciones para hoy
     *
     * @return Collection<Reservation>
     */
    public function getExpiringPendingReservations(Carbon $date): Collection
    {
        return Reservation::where(function ($query) use ($date) {
            // Caso 1: Reservas pendientes de confirmación que vencen hoy (falta seña)
            $query->where('status', Reservation::STATUS_PENDING_CONFIRMATION)
                ->whereNotNull('pending_until')
                ->whereDate('pending_until', $date);
        })
            ->orWhere(function ($query) use ($date) {
                // Caso 2: Reservas de hoy que aún no han saldado el pago (falta el saldo/balance)
                $query->whereDate('check_in_date', $date)
                    ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
                    ->whereDoesntHave('payments', function ($q) {
                        $q->where('payment_type', 'balance');
                    });
            })
            ->with(['client', 'cabin', 'payments'])
            ->orderBy('pending_until')
            ->orderBy('check_in_date')
            ->get();
    }
}
