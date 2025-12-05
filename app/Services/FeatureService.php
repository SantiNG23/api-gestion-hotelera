<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class FeatureService extends Service
{
    public function __construct()
    {
        parent::__construct(new Feature());
    }

    /**
     * Obtiene características con filtros aplicados
     */
    public function getFeatures(array $params): LengthAwarePaginator
    {
        $query = $this->model->query();
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene características activas
     */
    public function getActiveFeatures(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    /**
     * Obtiene una característica por ID
     */
    public function getFeature(int $id): Feature
    {
        return $this->getById($id);
    }

    /**
     * Crea una nueva característica
     */
    public function createFeature(array $data): Feature
    {
        return $this->create($data);
    }

    /**
     * Actualiza una característica existente
     */
    public function updateFeature(int $id, array $data): Feature
    {
        return $this->update($id, $data);
    }

    /**
     * Elimina una característica
     */
    public function deleteFeature(int $id): bool
    {
        return $this->delete($id);
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
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['name'];
    }
}

