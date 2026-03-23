<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ClientService extends Service
{
    public function __construct()
    {
        parent::__construct(new Client);
    }

    /**
     * Obtiene clientes con filtros aplicados
     */
    public function getClients(array $params): LengthAwarePaginator
    {
        $query = $this->model->query();
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene el reporte paginado de huéspedes recurrentes
     */
    public function getGuestsReport(array $params): LengthAwarePaginator
    {
        $this->requireTenantId();

        $dateRange = $params['date_range'] ?? [];
        $startDate = $params['start_date'] ?? $dateRange['start'] ?? null;
        $endDate = $params['end_date'] ?? $dateRange['end'] ?? null;

        $rangeStart = $startDate !== null
            ? Carbon::parse($startDate)->startOfDay()
            : null;
        $rangeEndExclusive = $endDate !== null
            ? Carbon::parse($endDate)->addDay()->startOfDay()
            : null;

        $query = $this->model->query()
            ->where('dni', '!=', Client::DNI_BLOCK)
            ->whereHas('reservations', fn (Builder $reservationQuery): Builder => $this->applyGuestVisitFilter($reservationQuery, $rangeStart, $rangeEndExclusive))
            ->withCount([
                'reservations as visits' => fn (Builder $reservationQuery): Builder => $this->applyGuestVisitFilter($reservationQuery, $rangeStart, $rangeEndExclusive),
            ])
            ->withMax([
                'reservations as last_stay' => fn (Builder $reservationQuery): Builder => $this->applyGuestVisitFilter($reservationQuery, $rangeStart, $rangeEndExclusive),
            ], 'check_in_date');

        $queryParams = $params;
        unset($queryParams['date_range']);

        $query = $this->getFilteredAndSorted($query, $queryParams);
        $query->orderBy('name');

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene un cliente por ID
     */
    public function getClient(int $id): Client
    {
        return $this->getById($id);
    }

    /**
     * Obtiene un cliente por ID con sus reservas
     */
    public function getClientWithReservations(int $id): Client
    {
        return $this->getByIdWith($id, [
            'reservations' => function ($query) {
                $query->with([
                    'cabin' => fn ($cabinQuery) => $cabinQuery->withTrashed(),
                ]);
            },
        ]);
    }

    /**
     * Busca un cliente por DNI con historial de reservas
     */
    public function searchByDni(string $dni): ?Client
    {
        return $this->model
            ->where('dni', $dni)
            ->with(['reservations' => function ($query) {
                $query->orderBy('check_in_date', 'desc')
                    ->with([
                        'cabin' => fn ($cabinQuery) => $cabinQuery->withTrashed(),
                    ]);
            }])
            ->first();
    }

    /**
     * Crea un nuevo cliente
     */
    public function createClient(array $data): Client
    {
        return $this->create($data);
    }

    /**
     * Actualiza un cliente existente
     */
    public function updateClient(int $id, array $data): Client
    {
        return $this->update($id, $data);
    }

    /**
     * Elimina un cliente
     */
    public function deleteClient(int $id): bool
    {
        $client = $this->getById($id);

        if ($client->dni === Client::DNI_BLOCK) {
            throw ValidationException::withMessages([
                'dni' => ['El cliente técnico de bloqueos no puede eliminarse'],
            ]);
        }

        return $this->delete($id);
    }

    /**
     * Filtro por DNI
     */
    protected function filterByDni(Builder $query, string $value): Builder
    {
        return $this->whereTextContains($query, 'dni', $value);
    }

    /**
     * Filtro de búsqueda para reporte de huéspedes
     */
    protected function filterByQuery(Builder $query, string $value): Builder
    {
        return $query->where(function (Builder $searchQuery) use ($value): void {
            $this->whereTextContains($searchQuery, 'name', $value);
            $this->whereTextContains($searchQuery, 'dni', $value, 'or');
        });
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['name', 'dni', 'city', 'phone', 'email'];
    }

    /**
     * Búsqueda simple para autocompletes de clientes
     */
    protected function applySimpleSearch(Builder $query, string $value): Builder
    {
        $query->select(['id', 'dni', 'name', 'phone', 'email'])
            ->where(function (Builder $searchQuery) use ($value): void {
                $this->whereTextContains($searchQuery, 'name', $value);
                $this->whereTextContains($searchQuery, 'dni', $value, 'or');
            });

        return $query;
    }

    private function applyGuestVisitFilter(
        Builder $query,
        ?Carbon $rangeStart = null,
        ?Carbon $rangeEndExclusive = null
    ): Builder {
        $query = $query
            ->where('is_blocked', false)
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_FINISHED,
            ]);

        if ($rangeStart !== null && $rangeEndExclusive !== null) {
            $query
                ->whereDate('check_in_date', '<', $rangeEndExclusive->toDateString())
                ->whereDate('check_out_date', '>', $rangeStart->toDateString());
        }

        return $query;
    }
}
