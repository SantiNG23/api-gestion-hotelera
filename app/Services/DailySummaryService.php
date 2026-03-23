<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cabin;
use App\Models\Reservation;
use App\Models\ReservationPayment;
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
     * @return array{has_events: bool, occupied_cabins: int, total_cabins: int, check_ins: Collection, check_outs: Collection, expiring_pending: Collection}
     */
    public function getDailySummary(?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();

        $occupiedCabins = $this->getOccupiedCabinsCountForDate($date);
        $totalCabins = $this->getTotalCabinsCount();
        $checkIns = $this->getCheckInsForDate($date);
        $checkOuts = $this->getCheckOutsForDate($date);
        $expiringPending = $this->getExpiringPendingReservations($date);

        $hasEvents = $checkIns->isNotEmpty()
            || $checkOuts->isNotEmpty()
            || $expiringPending->isNotEmpty();

        return [
            'has_events' => $hasEvents,
            'occupied_cabins' => $occupiedCabins,
            'total_cabins' => $totalCabins,
            'check_ins' => $checkIns,
            'check_outs' => $checkOuts,
            'expiring_pending' => $expiringPending,
        ];
    }

    public function getOccupiedCabinsCountForDate(Carbon $date): int
    {
        return Reservation::query()
            ->where('is_blocked', false)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->whereDate('check_in_date', '<=', $date)
            ->whereDate('check_out_date', '>', $date)
            ->distinct('cabin_id')
            ->count('cabin_id');
    }

    public function getTotalCabinsCount(): int
    {
        return Cabin::query()
            ->where('is_active', true)
            ->count();
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
            ->with([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
            ])
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
            ->where('is_blocked', false)
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
            ])
            ->with([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
            ])
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
            ->with([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'payments',
            ])
            ->orderBy('pending_until')
            ->orderBy('check_in_date')
            ->get();
    }

    /**
     * @return array{pending_deposits: int, scheduled_check_ins: int, estimated_occupancy: float}
     */
    public function getReportsSummary(Carbon $startDate, Carbon $endDate, ?int $cabinId = null): array
    {
        $pendingDeposits = $this->baseReportsReservationQuery($startDate, $endDate, $cabinId)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->whereDoesntHave('payments', function ($query) {
                $query->where('payment_type', ReservationPayment::TYPE_DEPOSIT);
            })
            ->count();

        $scheduledCheckIns = $this->baseReportsReservationQuery($startDate, $endDate, $cabinId)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->count();

        return [
            'pending_deposits' => $pendingDeposits,
            'scheduled_check_ins' => $scheduledCheckIns,
            'estimated_occupancy' => $this->calculateEstimatedOccupancy($startDate, $endDate, $cabinId),
        ];
    }

    private function baseReportsReservationQuery(Carbon $startDate, Carbon $endDate, ?int $cabinId = null): \Illuminate\Database\Eloquent\Builder
    {
        return Reservation::query()
            ->where('is_blocked', false)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN, Reservation::STATUS_FINISHED])
            ->when($cabinId !== null, function ($query) use ($cabinId) {
                $query->where('cabin_id', $cabinId);
            })
            ->whereBetween('check_in_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);
    }

    private function calculateEstimatedOccupancy(Carbon $startDate, Carbon $endDate, ?int $cabinId = null): float
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEndExclusive = $endDate->copy()->addDay()->startOfDay();
        $totalDays = $rangeStart->diffInDays($endDate->copy()->startOfDay()) + 1;

        $inventoryCount = $cabinId !== null
            ? Cabin::whereKey($cabinId)->count()
            : Cabin::where('is_active', true)->count();

        if ($inventoryCount === 0 || $totalDays === 0) {
            return 0.0;
        }

        $occupiedUnits = Reservation::query()
            ->where('is_blocked', false)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN, Reservation::STATUS_FINISHED])
            ->when($cabinId !== null, function ($query) use ($cabinId) {
                $query->where('cabin_id', $cabinId);
            })
            ->whereDate('check_in_date', '<', $rangeEndExclusive->toDateString())
            ->whereDate('check_out_date', '>', $rangeStart->toDateString())
            ->get(['check_in_date', 'check_out_date'])
            ->sum(function (Reservation $reservation) use ($rangeStart, $rangeEndExclusive): int {
                $occupiedStart = $reservation->check_in_date->copy()->startOfDay()->max($rangeStart);
                $occupiedEnd = $reservation->check_out_date->copy()->startOfDay()->min($rangeEndExclusive);

                return $occupiedEnd->lessThanOrEqualTo($occupiedStart)
                    ? 0
                    : (int) $occupiedStart->diffInDays($occupiedEnd);
            });

        return round(($occupiedUnits / ($inventoryCount * $totalDays)) * 100, 2);
    }
}
