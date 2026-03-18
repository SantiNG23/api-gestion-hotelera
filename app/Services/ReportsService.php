<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportsService
{
    private const OPERATIONAL_STATUSES = [
        Reservation::STATUS_CONFIRMED,
        Reservation::STATUS_CHECKED_IN,
        Reservation::STATUS_FINISHED,
    ];

    /**
     * @return array{occupancy_rate: float, nights_sold: int, total_reservations: int, operational_revenue: float}
     */
    public function getSummary(Carbon $startDate, Carbon $endDate, ?int $cabinId = null): array
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEndExclusive = $endDate->copy()->addDay()->startOfDay();

        $reservations = $this->reservationReportQuery($rangeStart, $rangeEndExclusive, $cabinId)->get();
        $operationalReservations = $reservations->filter(
            fn (Reservation $reservation): bool => in_array($reservation->status, self::OPERATIONAL_STATUSES, true)
        );
        $occupancyItems = $this->getOccupancy($startDate, $endDate, $cabinId);
        $totalAvailableNights = array_sum(array_column($occupancyItems, 'total_nights'));
        $occupiedNights = array_sum(array_column($occupancyItems, 'occupied_nights'));

        return [
            'occupancy_rate' => $totalAvailableNights === 0
                ? 0.0
                : round(($occupiedNights / $totalAvailableNights) * 100, 2),
            'nights_sold' => $operationalReservations->sum(
                fn (Reservation $reservation): int => $this->calculateOverlappingNights($reservation, $rangeStart, $rangeEndExclusive)
            ),
            'total_reservations' => $reservations->count(),
            'operational_revenue' => round($operationalReservations->sum(
                fn (Reservation $reservation): float => $this->calculateRevenueWithinRange($reservation, $rangeStart, $rangeEndExclusive)
            ), 2),
        ];
    }

    /**
     * @return array{total_revenue: float, reservations: LengthAwarePaginator}
     */
    public function getReservationsReport(array $filters): array
    {
        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDateExclusive = Carbon::parse($filters['end_date'])->addDay()->startOfDay();
        $cabinId = isset($filters['cabin_id']) ? (int) $filters['cabin_id'] : null;
        $page = isset($filters['page']) ? max((int) $filters['page'], 1) : 1;
        $perPage = isset($filters['per_page']) ? max((int) $filters['per_page'], 1) : 10;

        $query = $this->reservationReportQuery($startDate, $endDateExclusive, $cabinId);

        if (! empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('client', function (Builder $clientQuery) use ($search): void {
                    $clientQuery->withTrashed()
                        ->where(function (Builder $nestedQuery) use ($search): void {
                            $nestedQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('dni', 'like', "%{$search}%");
                        });
                })->orWhereHas('cabin', function (Builder $cabinQuery) use ($search): void {
                    $cabinQuery->withTrashed()->where('name', 'like', "%{$search}%");
                });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'check_in_date';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        if (! in_array($sortBy, ['check_in_date', 'check_out_date', 'status', 'total_price'], true)) {
            $sortBy = 'check_in_date';
        }
        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        $reservations = (clone $query)
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        foreach ($reservations->getCollection() as $reservation) {
            $reservation->setAttribute('report_nights', $this->calculateOverlappingNights($reservation, $startDate, $endDateExclusive));
        }

        $operationalRevenue = $this->calculateOperationalRevenue($query, $startDate, $endDateExclusive);

        return [
            'total_revenue' => round((float) $operationalRevenue, 2),
            'reservations' => $reservations,
        ];
    }

    /**
     * @return list<array{cabin_id: int, cabin_name: string, occupancy_rate: float, occupied_nights: int, total_nights: int}>
     */
    public function getOccupancy(Carbon $startDate, Carbon $endDate, ?int $cabinId = null): array
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEndExclusive = $endDate->copy()->addDay()->startOfDay();
        $totalNights = $rangeStart->diffInDays($rangeEndExclusive);

        if ($totalNights <= 0) {
            return [];
        }

        $cabins = Cabin::query()
            ->when($cabinId !== null, fn (Builder $query): Builder => $query->whereKey($cabinId))
            ->when($cabinId === null, fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($cabins->isEmpty()) {
            return [];
        }

        $occupiedNightsByCabin = Reservation::query()
            ->blocking()
            ->when($cabinId !== null, fn (Builder $query): Builder => $query->where('cabin_id', $cabinId))
            ->whereDate('check_in_date', '<', $rangeEndExclusive->toDateString())
            ->whereDate('check_out_date', '>', $rangeStart->toDateString())
            ->get(['cabin_id', 'check_in_date', 'check_out_date'])
            ->groupBy('cabin_id')
            ->map(fn (Collection $reservations): int => $reservations->sum(
                fn (Reservation $reservation): int => $this->calculateOverlappingNights($reservation, $rangeStart, $rangeEndExclusive)
            ));

        return $cabins
            ->map(function (Cabin $cabin) use ($occupiedNightsByCabin, $totalNights): array {
                $occupiedNights = (int) ($occupiedNightsByCabin[$cabin->id] ?? 0);

                return [
                    'cabin_id' => $cabin->id,
                    'cabin_name' => $cabin->name,
                    'occupancy_rate' => round(($occupiedNights / $totalNights) * 100, 2),
                    'occupied_nights' => $occupiedNights,
                    'total_nights' => $totalNights,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getHistoryByDni(string $dni): Collection
    {
        if ($dni === '' || $dni === Client::DNI_BLOCK) {
            return new Collection;
        }

        return Reservation::query()
            ->with([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
            ])
            ->where('is_blocked', false)
            ->whereHas('client', fn (Builder $query): Builder => $query->withTrashed()->where('dni', $dni))
            ->orderByDesc('check_in_date')
            ->get();
    }

    private function reservationReportQuery(Carbon $rangeStart, Carbon $rangeEndExclusive, ?int $cabinId = null): Builder
    {
        return Reservation::query()
            ->with([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
            ])
            ->where('is_blocked', false)
            ->when($cabinId !== null, fn (Builder $query): Builder => $query->where('cabin_id', $cabinId))
            ->whereDate('check_in_date', '<', $rangeEndExclusive->toDateString())
            ->whereDate('check_out_date', '>', $rangeStart->toDateString());
    }

    private function calculateOverlappingNights(Reservation $reservation, Carbon $rangeStart, Carbon $rangeEndExclusive): int
    {
        $occupiedStart = $reservation->check_in_date->copy()->startOfDay()->max($rangeStart);
        $occupiedEnd = $reservation->check_out_date->copy()->startOfDay()->min($rangeEndExclusive);

        if ($occupiedEnd->lessThanOrEqualTo($occupiedStart)) {
            return 0;
        }

        return (int) $occupiedStart->diffInDays($occupiedEnd);
    }

    private function calculateRevenueWithinRange(Reservation $reservation, Carbon $rangeStart, Carbon $rangeEndExclusive): float
    {
        $reservationNights = max((int) $reservation->nights, 0);
        $overlappingNights = $this->calculateOverlappingNights($reservation, $rangeStart, $rangeEndExclusive);

        if ($reservationNights === 0 || $overlappingNights === 0) {
            return 0.0;
        }

        return round(((float) $reservation->total_price / $reservationNights) * $overlappingNights, 2);
    }

    private function calculateOperationalRevenue(Builder $query, Carbon $rangeStart, Carbon $rangeEndExclusive): float
    {
        $totalRevenue = 0.0;

        foreach ((clone $query)
            ->select(['id', 'status', 'check_in_date', 'check_out_date', 'nights', 'total_price'])
            ->orderBy('id')
            ->cursor() as $reservation) {
            /** @var Reservation $reservation */
            if (! in_array($reservation->status, self::OPERATIONAL_STATUSES, true)) {
                continue;
            }

            $totalRevenue += $this->calculateRevenueWithinRange($reservation, $rangeStart, $rangeEndExclusive);
        }

        return $totalRevenue;
    }
}
