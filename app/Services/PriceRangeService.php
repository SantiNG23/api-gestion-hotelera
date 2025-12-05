<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PriceGroup;
use App\Models\PriceRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PriceRangeService extends Service
{
    public function __construct()
    {
        parent::__construct(new PriceRange());
    }

    /**
     * Obtiene rangos de precio con filtros aplicados
     */
    public function getPriceRanges(array $params): LengthAwarePaginator
    {
        $query = $this->model->query()->with('priceGroup');
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene un rango de precio por ID
     */
    public function getPriceRange(int $id): PriceRange
    {
        return $this->getByIdWith($id, ['priceGroup']);
    }

    /**
     * Crea un nuevo rango de precio
     *
     * @throws ValidationException
     */
    public function createPriceRange(array $data): PriceRange
    {
        // Validar que price_group_id pertenezca al mismo tenant
        $this->validatePriceGroupTenant($data['price_group_id']);

        // Asegurar que tenant_id coincida con el del price_group
        $priceGroup = PriceGroup::findOrFail($data['price_group_id']);
        $data['tenant_id'] = $priceGroup->tenant_id;

        // Validar solapamiento de fechas
        $this->validateNoOverlap(
            $data['start_date'],
            $data['end_date'],
            $data['tenant_id']
        );

        return $this->create($data);
    }

    /**
     * Actualiza un rango de precio existente
     *
     * @throws ValidationException
     */
    public function updatePriceRange(int $id, array $data): PriceRange
    {
        $priceRange = $this->getById($id);

        if (isset($data['price_group_id'])) {
            $this->validatePriceGroupTenant($data['price_group_id']);
        }

        // Validar solapamiento de fechas (excluyendo el rango actual)
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $this->validateNoOverlap(
                $data['start_date'] ?? $priceRange->start_date->format('Y-m-d'),
                $data['end_date'] ?? $priceRange->end_date->format('Y-m-d'),
                $priceRange->tenant_id,
                $id
            );
        }

        return $this->update($id, $data);
    }

    /**
     * Elimina un rango de precio
     */
    public function deletePriceRange(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Obtiene el rango de precio aplicable para una fecha específica
     */
    public function getPriceRangeForDate(Carbon $date): ?PriceRange
    {
        return $this->model
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with('priceGroup')
            ->first();
    }

    /**
     * Valida que el grupo de precio pertenezca al tenant actual
     *
     * @throws ValidationException
     */
    private function validatePriceGroupTenant(int $priceGroupId): void
    {
        $priceGroup = PriceGroup::find($priceGroupId);

        if (!$priceGroup) {
            throw ValidationException::withMessages([
                'price_group_id' => ['El grupo de precio no existe'],
            ]);
        }

        $tenantId = Auth::user()?->tenant_id;

        if ($tenantId && $priceGroup->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'price_group_id' => ['El grupo de precio no pertenece a este tenant'],
            ]);
        }
    }

    /**
     * Valida que no haya solapamiento de fechas con otros rangos
     *
     * @throws ValidationException
     */
    private function validateNoOverlap(
        string $startDate,
        string $endDate,
        int $tenantId,
        ?int $excludeId = null
    ): void {
        $query = $this->model
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $q) use ($startDate, $endDate) {
                // El nuevo rango se solapa si:
                // - Su inicio está dentro de un rango existente
                // - Su fin está dentro de un rango existente
                // - Envuelve completamente un rango existente
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->whereDate('start_date', '<=', $endDate)
                        ->whereDate('end_date', '>=', $startDate);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => ['Las fechas se solapan con otro rango de precios existente'],
            ]);
        }
    }

    /**
     * Filtro por grupo de precio
     */
    protected function filterByPriceGroupId(Builder $query, int $value): Builder
    {
        return $query->where('price_group_id', $value);
    }

    /**
     * Campo de ordenamiento por defecto
     */
    protected function getDateColumn(): string
    {
        return 'start_date';
    }
}
