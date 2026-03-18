<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

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

        $query = $this->model->query()
            ->where('dni', '!=', Client::DNI_BLOCK)
            ->whereHas('reservations', fn (Builder $reservationQuery): Builder => $this->applyGuestVisitFilter($reservationQuery))
            ->withCount([
                'reservations as visits' => fn (Builder $reservationQuery): Builder => $this->applyGuestVisitFilter($reservationQuery),
            ])
            ->withMax([
                'reservations as last_stay' => fn (Builder $reservationQuery): Builder => $this->applyGuestVisitFilter($reservationQuery),
            ], 'check_in_date');

        $query = $this->getFilteredAndSorted($query, $params);
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
        return $this->delete($id);
    }

    /**
     * Filtro por DNI
     */
    protected function filterByDni(Builder $query, string $value): Builder
    {
        return $query->where('dni', 'like', "%{$value}%");
    }

    /**
     * Filtro de búsqueda para reporte de huéspedes
     */
    protected function filterByQuery(Builder $query, string $value): Builder
    {
        return $query->where(function (Builder $searchQuery) use ($value): void {
            $searchQuery->where('name', 'like', "%{$value}%")
                ->orWhere('dni', 'like', "%{$value}%");
        });
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['name', 'dni', 'city', 'phone', 'email'];
    }

    private function applyGuestVisitFilter(Builder $query): Builder
    {
        return $query
            ->where('is_blocked', false)
            ->whereIn('status', [
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_FINISHED,
            ]);
    }
}
