<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cabin;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio utilitario para verificar disponibilidad de cabañas
 *
 * NO extiende Service porque no maneja CRUD de una entidad
 */
class AvailabilityService
{
    /**
     * Verifica si una cabaña está disponible para un rango de fechas
     *
     * @param  int  $cabinId  ID de la cabaña
     * @param  Carbon  $checkIn  Fecha de check-in
     * @param  Carbon  $checkOut  Fecha de check-out
     * @param  int|null  $excludeReservationId  ID de reserva a excluir (para ediciones)
     */
    public function isAvailable(int $cabinId, Carbon $checkIn, Carbon $checkOut, ?int $excludeReservationId = null): bool
    {
        $query = Reservation::where('cabin_id', $cabinId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->where(function ($q) use ($checkIn, $checkOut) {
                // Detectar solapamiento:
                // Una reserva existente se solapa si:
                // - Su check_in es antes del nuevo check_out Y
                // - Su check_out es después del nuevo check_in
                $q->whereDate('check_in_date', '<', $checkOut)
                    ->whereDate('check_out_date', '>', $checkIn);
            });

        // Excluir reservas pendientes que ya vencieron
        $query->where(function ($q) {
            $q->where('status', '!=', Reservation::STATUS_PENDING_CONFIRMATION)
                ->orWhere(function ($q2) {
                    $q2->where('status', Reservation::STATUS_PENDING_CONFIRMATION)
                        ->where(function ($q3) {
                            $q3->whereNull('pending_until')
                                ->orWhere('pending_until', '>', now());
                        });
                });
        });

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return !$query->exists();
    }

    /**
     * Obtiene las cabañas disponibles para un rango de fechas
     *
     * @param  Carbon  $checkIn  Fecha de check-in
     * @param  Carbon  $checkOut  Fecha de check-out
     * @return Collection<Cabin>
     */
    public function getAvailableCabins(Carbon $checkIn, Carbon $checkOut): Collection
    {
        // Obtener IDs de cabañas con reservas que bloquean
        $blockedCabinIds = Reservation::whereIn('status', Reservation::BLOCKING_STATUSES)
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereDate('check_in_date', '<', $checkOut)
                    ->whereDate('check_out_date', '>', $checkIn);
            })
            ->where(function ($q) {
                $q->where('status', '!=', Reservation::STATUS_PENDING_CONFIRMATION)
                    ->orWhere(function ($q2) {
                        $q2->where('status', Reservation::STATUS_PENDING_CONFIRMATION)
                            ->where(function ($q3) {
                                $q3->whereNull('pending_until')
                                    ->orWhere('pending_until', '>', now());
                            });
                    });
            })
            ->pluck('cabin_id')
            ->unique();

        return Cabin::where('is_active', true)
            ->whereNotIn('id', $blockedCabinIds)
            ->with('features')
            ->get();
    }

    /**
     * Obtiene las reservas que bloquean una cabaña en un rango de fechas
     *
     * @param  int  $cabinId  ID de la cabaña
     * @param  Carbon  $from  Fecha desde
     * @param  Carbon  $to  Fecha hasta
     * @return Collection<Reservation>
     */
    public function getBlockingReservations(int $cabinId, Carbon $from, Carbon $to): Collection
    {
        return Reservation::where('cabin_id', $cabinId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->where(function ($q) use ($from, $to) {
                $q->whereDate('check_in_date', '<', $to)
                    ->whereDate('check_out_date', '>', $from);
            })
            ->where(function ($q) {
                $q->where('status', '!=', Reservation::STATUS_PENDING_CONFIRMATION)
                    ->orWhere(function ($q2) {
                        $q2->where('status', Reservation::STATUS_PENDING_CONFIRMATION)
                            ->where(function ($q3) {
                                $q3->whereNull('pending_until')
                                    ->orWhere('pending_until', '>', now());
                            });
                    });
            })
            ->with(['client', 'cabin'])
            ->orderBy('check_in_date')
            ->get();
    }

    /**
     * Obtiene rangos bloqueados de una cabaña en un período específico
     * Formato para el endpoint /availability/{cabin_id}
     *
     * @param  int  $cabinId  ID de la cabaña
     * @param  Carbon  $from  Fecha desde
     * @param  Carbon  $to  Fecha hasta
     * @return array{from: string, to: string, blocked_ranges: array}
     */
    public function getBlockedRanges(int $cabinId, Carbon $from, Carbon $to): array
    {
        $reservations = $this->getBlockingReservations($cabinId, $from, $to);

        $blockedRanges = $reservations->map(function (Reservation $reservation) {
            return [
                'from' => $reservation->check_in_date->format('Y-m-d'),
                'to' => $reservation->check_out_date->format('Y-m-d'),
                'status' => $reservation->status,
                'id' => $reservation->id,
            ];
        })->values()->toArray();

        return [
            'cabin_id' => $cabinId,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'blocked_ranges' => $blockedRanges,
        ];
    }

    /**
     * Obtiene el calendario de disponibilidad con reservas agrupadas por cabaña
     * Formato para el endpoint /availability/calendar
     *
     * @param  Carbon  $from  Fecha desde
     * @param  Carbon  $to  Fecha hasta
     * @return array{from: string, to: string, cabins: array}
     */
    public function getCalendarDays(Carbon $from, Carbon $to): array
    {
        $cabins = Cabin::where('is_active', true)->get();

        $calendarCabins = $cabins->map(function (Cabin $cabin) use ($from, $to) {
            // Obtener reservas que se solapan con el rango de fechas
            $reservations = Reservation::where('cabin_id', $cabin->id)
                ->whereIn('status', Reservation::BLOCKING_STATUSES)
                ->where(function ($q) use ($from, $to) {
                    $q->whereDate('check_in_date', '<', $to)
                        ->whereDate('check_out_date', '>', $from);
                })
                ->where(function ($q) {
                    $q->where('status', '!=', Reservation::STATUS_PENDING_CONFIRMATION)
                        ->orWhere(function ($q2) {
                            $q2->where('status', Reservation::STATUS_PENDING_CONFIRMATION)
                                ->where(function ($q3) {
                                    $q3->whereNull('pending_until')
                                        ->orWhere('pending_until', '>', now());
                                });
                        });
                })
                ->with('client')
                ->orderBy('check_in_date')
                ->get();

            return [
                'id' => $cabin->id,
                'name' => $cabin->name,
                'reservations' => $reservations->map(function (Reservation $reservation) {
                    return [
                        'id' => $reservation->id,
                        'client_name' => $reservation->client->name,
                        'check_in_date' => $reservation->check_in_date->format('Y-m-d'),
                        'check_out_date' => $reservation->check_out_date->format('Y-m-d'),
                        'status' => $reservation->status,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'cabins' => $calendarCabins,
        ];
    }

    /**
     * Obtiene el estado de un día específico para una cabaña
     *
     * @param  int  $cabinId  ID de la cabaña
     * @param  Carbon  $date  Fecha a verificar
     * @return array{date: string, status: string, reservation_id?: int}
     */
    private function getDayStatus(int $cabinId, Carbon $date): array
    {
        // Buscar una reserva que cubra este día
        $reservation = Reservation::where('cabin_id', $cabinId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->where(function ($q) use ($date) {
                $q->whereDate('check_in_date', '<=', $date)
                    ->whereDate('check_out_date', '>', $date);
            })
            ->where(function ($q) {
                $q->where('status', '!=', Reservation::STATUS_PENDING_CONFIRMATION)
                    ->orWhere(function ($q2) {
                        $q2->where('status', Reservation::STATUS_PENDING_CONFIRMATION)
                            ->where(function ($q3) {
                                $q3->whereNull('pending_until')
                                    ->orWhere('pending_until', '>', now());
                            });
                    });
            })
            ->first();

        if (!$reservation) {
            return [
                'date' => $date->format('Y-m-d'),
                'status' => 'free',
            ];
        }

        $result = [
            'date' => $date->format('Y-m-d'),
            'status' => $reservation->status,
            'reservation_id' => $reservation->id,
        ];

        return $result;
    }
}
