<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PriceGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class PriceGroupService extends Service
{
    public function __construct()
    {
        parent::__construct(new PriceGroup());
    }

    /**
     * Obtiene grupos de precios con filtros aplicados
     */
    public function getPriceGroups(array $params): LengthAwarePaginator
    {
        $query = $this->model->query()->with('priceRanges');
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene un grupo de precio por ID
     */
    public function getPriceGroup(int $id): PriceGroup
    {
        return $this->getByIdWith($id, ['priceRanges']);
    }

    /**
     * Obtiene el grupo de precio por defecto del tenant actual
     */
    public function getDefaultPriceGroup(): ?PriceGroup
    {
        return $this->model->where('is_default', true)->first();
    }

    /**
     * Crea un nuevo grupo de precio
     */
    public function createPriceGroup(array $data): PriceGroup
    {
        // Si se marca como default, desactivar otros defaults del mismo tenant
        if (!empty($data['is_default'])) {
            $tenantId = $data['tenant_id'] ?? Auth::user()->tenant_id;
            PriceGroup::where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->where('deleted_at', null)
                ->update(['is_default' => false]);
        }

        return $this->create($data);
    }

    /**
     * Actualiza un grupo de precio existente
     */
    public function updatePriceGroup(int $id, array $data): PriceGroup
    {
        // Si se marca como default, desactivar otros defaults del mismo tenant
        if (!empty($data['is_default'])) {
            $priceGroup = $this->getById($id);
            PriceGroup::where('tenant_id', $priceGroup->tenant_id)
                ->where('id', '!=', $id)
                ->where('is_default', true)
                ->where('deleted_at', null)
                ->update(['is_default' => false]);
        }

        return $this->update($id, $data);
    }

    /**
     * Elimina un grupo de precio
     */
    public function deletePriceGroup(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['name'];
    }

    /**
     * Filtro por is_default
     */
    protected function filterByIsDefault(Builder $query, mixed $value): Builder
    {
        $isDefault = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return $query->where('is_default', $isDefault);
    }
}
