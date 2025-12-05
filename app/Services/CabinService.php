<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cabin;
use App\Models\Feature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CabinService extends Service
{
    public function __construct()
    {
        parent::__construct(new Cabin());
    }

    /**
     * Obtiene cabañas con filtros aplicados
     */
    public function getCabins(array $params): LengthAwarePaginator
    {
        $query = $this->model->query()->with('features');
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene cabañas activas
     */
    public function getActiveCabins(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->where('is_active', true)->with('features')->get();
    }

    /**
     * Obtiene una cabaña por ID
     */
    public function getCabin(int $id): Cabin
    {
        return $this->getByIdWith($id, ['features']);
    }

    /**
     * Crea una nueva cabaña
     */
    public function createCabin(array $data): Cabin
    {
        $featureIds = $data['feature_ids'] ?? [];
        unset($data['feature_ids']);

        $cabin = $this->create($data);

        if (!empty($featureIds)) {
            $this->syncFeatures($cabin, $featureIds);
        }

        return $cabin->load('features');
    }

    /**
     * Actualiza una cabaña existente
     */
    public function updateCabin(int $id, array $data): Cabin
    {
        $featureIds = $data['feature_ids'] ?? null;
        unset($data['feature_ids']);

        $cabin = $this->update($id, $data);

        if ($featureIds !== null) {
            $this->syncFeatures($cabin, $featureIds);
        }

        return $cabin->load('features');
    }

    /**
     * Elimina una cabaña
     */
    public function deleteCabin(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Sincroniza las características de una cabaña
     * Valida que las características pertenezcan al mismo tenant
     */
    public function syncFeatures(Cabin $cabin, array $featureIds): void
    {
        if (empty($featureIds)) {
            $cabin->features()->detach();

            return;
        }

        // Validar que todas las características pertenezcan al mismo tenant
        $validFeatureIds = Feature::where('tenant_id', $cabin->tenant_id)
            ->whereIn('id', $featureIds)
            ->pluck('id')
            ->toArray();

        $cabin->features()->sync($validFeatureIds);
    }

    /**
     * Filtro por estado activo
     */
    protected function filterByIsActive(Builder $query, mixed $value): Builder
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return $query->where('is_active', $isActive);
    }

    /**
     * Filtro por capacidad mínima
     */
    protected function filterByMinCapacity(Builder $query, mixed $value): Builder
    {
        $value = (int) $value;
        return $query->where('capacity', '>=', $value);
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['name', 'description'];
    }
}
