<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CabinPriceByGuestsService extends Service
{
    public function __construct()
    {
        parent::__construct(new CabinPriceByGuests);
    }

    /**
     * Obtiene precios de cabañas por cantidad de huéspedes con filtros aplicados
     */
    public function getCabinPricesByGuests(array $params): LengthAwarePaginator
    {
        $query = $this->model->query()->with(['cabin', 'priceGroup']);
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene un precio de cabaña por cantidad de huéspedes por ID
     */
    public function getCabinPriceByGuests(int $id): CabinPriceByGuests
    {
        return $this->getByIdWith($id, ['cabin', 'priceGroup']);
    }

    /**
     * Obtiene precios de una cabaña específica
     */
    public function getPricesByCabin(int $cabinId, array $params = []): LengthAwarePaginator
    {
        $params = array_merge([
            'page' => 1,
            'per_page' => 50,
            'sort_by' => 'num_guests',
            'sort_order' => 'asc',
            'filters' => [],
            'date_range' => null,
        ], $params);

        $query = $this->model->query()
            ->where('cabin_id', $cabinId)
            ->with(['cabin', 'priceGroup']);

        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene el precio de una cabaña para una cantidad específica de huéspedes y grupo de precio
     */
    public function getPriceForCabinAndGuests(int $cabinId, int $numGuests, int $priceGroupId): ?CabinPriceByGuests
    {
        return $this->model->query()
            ->where('cabin_id', $cabinId)
            ->where('num_guests', $numGuests)
            ->where('price_group_id', $priceGroupId)
            ->first();
    }

    /**
     * Crea un nuevo precio de cabaña por cantidad de huéspedes
     */
    public function createCabinPriceByGuests(array $data): CabinPriceByGuests
    {
        $this->guardTenantIntegrity($data);

        return $this->create($data);
    }

    /**
     * Actualiza un precio de cabaña por cantidad de huéspedes
     */
    public function updateCabinPriceByGuests(int $id, array $data): CabinPriceByGuests
    {
        $current = CabinPriceByGuests::query()->findOrFail($id);

        $this->guardTenantIntegrity(array_merge([
            'cabin_id' => $current->cabin_id,
            'price_group_id' => $current->price_group_id,
        ], $data));

        return $this->update($id, $data);
    }

    /**
     * Elimina un precio de cabaña por cantidad de huéspedes
     */
    public function deleteCabinPriceByGuests(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Elimina todos los precios de una cabaña para un grupo de precio específico
     */
    public function deletePricesByCabinAndGroup(int $cabinId, int $priceGroupId): int
    {
        return $this->model->query()
            ->where('cabin_id', $cabinId)
            ->where('price_group_id', $priceGroupId)
            ->delete();
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return [];
    }

    /**
     * Filtro por cabin_id
     */
    protected function filterByCabinId(Builder $query, mixed $value): Builder
    {
        return $query->where('cabin_id', $value);
    }

    /**
     * Filtro por price_group_id
     */
    protected function filterByPriceGroupId(Builder $query, mixed $value): Builder
    {
        return $query->where('price_group_id', $value);
    }

    /**
     * Filtro por num_guests
     */
    protected function filterByNumGuests(Builder $query, mixed $value): Builder
    {
        return $query->where('num_guests', $value);
    }

    private function guardTenantIntegrity(array $data): void
    {
        $tenantId = $this->requireTenantId();
        $cabinId = (int) ($data['cabin_id'] ?? 0);
        $priceGroupId = (int) ($data['price_group_id'] ?? 0);

        $cabinExists = Cabin::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($cabinId)
            ->where('tenant_id', $tenantId)
            ->exists();

        $priceGroupExists = PriceGroup::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($priceGroupId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $cabinExists) {
            throw ValidationException::withMessages([
                'cabin_id' => ['La cabaña no pertenece al tenant activo.'],
            ]);
        }

        if (! $priceGroupExists) {
            throw ValidationException::withMessages([
                'price_group_id' => ['El grupo de precio no pertenece al tenant activo.'],
            ]);
        }
    }
}
