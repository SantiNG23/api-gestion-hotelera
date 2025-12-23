<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cabin;
use App\Models\Feature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
    public function getActiveCabins(): Collection
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
     * Crea una nueva cabaña y sincroniza sus características
     */
    public function createCabin(array $data): Cabin
    {
        return DB::transaction(function () use ($data) {
            // Extrae y remueve feature_ids en un solo paso
            $featureIds = Arr::pull($data, 'feature_ids', []);
            
            $cabin = $this->create($data);

            // Sincronizar solo si hay IDs (ahorra una query si está vacío)
            if (!empty($featureIds)) {
                $this->syncFeatures($cabin, $featureIds);
            }

            return $cabin->load('features');
        });
    }

    /**
     * Actualiza una cabaña existente
     */
    public function updateCabin(int $id, array $data): Cabin
    {
        return DB::transaction(function () use ($id, $data) {
            $featureIds = Arr::pull($data, 'feature_ids');
            $cabin = $this->update($id, $data);

            if ($featureIds !== null) {
                $this->syncFeatures($cabin, $featureIds);
            }

            return $cabin->load('features');
        });
    }

    /**
     * Elimina una cabaña
     */
    public function deleteCabin(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Sincroniza las características de una cabaña asegurando la integridad del tenant
     */
    public function syncFeatures(Cabin $cabin, array $featureIds): void
    {
        // Si no hay ids, sync([]) hace automáticamente un detach()
        // y nos ahorramos la query de filtrado.
        if (empty($featureIds)) {
            $cabin->features()->detach();
            return;
        }

        // Filtramos los IDs para que solo queden los que pertenecen al tenant de la cabaña
        $validFeatureIds = Feature::where('tenant_id', $cabin->tenant_id)
            ->whereIn('id', $featureIds)
            ->pluck('id');

        $cabin->features()->sync($validFeatureIds);
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
