<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientService extends Service
{
    public function __construct()
    {
        parent::__construct(new Client());
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
        return $this->getByIdWith($id, ['reservations', 'reservations.cabin']);
    }

    /**
     * Busca un cliente por DNI con historial de reservas
     */
    public function searchByDni(string $dni): ?Client
    {
        return $this->model
            ->where('dni', $dni)
            ->with(['reservations' => function ($query) {
                $query->orderBy('check_in_date', 'desc');
            }, 'reservations.cabin'])
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
     * Filtro por nombre
     */
    protected function filterByName(Builder $query, string $value): Builder
    {
        return $query->where('name', 'like', "%{$value}%");
    }

    /**
     * Filtro por DNI
     */
    protected function filterByDni(Builder $query, string $value): Builder
    {
        return $query->where('dni', 'like', "%{$value}%");
    }

    /**
     * Filtro por ciudad
     */
    protected function filterByCity(Builder $query, string $value): Builder
    {
        return $query->where('city', 'like', "%{$value}%");
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['name', 'dni', 'city', 'phone', 'email'];
    }
}

