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
     * Obtiene las tarifas aplicables para un rango de fechas con algoritmo de prioridad
     *
     * Algoritmo: Para cada día en el rango, selecciona el precio ganador basado en:
     * 1. Prioridad del grupo (DESC) - mayor prioridad gana
     * 2. created_at del rango (DESC) - el más reciente gana en caso de empate
     *
     * @return array<string, float> Array con formato ["2025-01-01" => 150.00, ...]
     */
    public function getApplicableRates(
        string $startDate,
        string $endDate,
        ?int $tenantId = null
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        if (!$tenantId) {
            $tenantId = Auth::user()?->tenant_id;
        }

        // Obtener todos los rangos que tocan el período
        $priceRanges = $this->model
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('end_date', '>=', $start)
            ->where('start_date', '<=', $end)
            ->with(['priceGroup' => function ($query) {
                $query->withoutGlobalScope('tenant');
            }])
            ->get();

        $result = [];
        $currentDate = $start->copy();

        // Iterar por cada día del rango
        while ($currentDate <= $end) {
            $dayString = $currentDate->format('Y-m-d');

            // Filtrar rangos activos para este día
            $activePriceRanges = $priceRanges->filter(function ($range) use ($currentDate) {
                return $range->start_date <= $currentDate->toDate()
                    && $range->end_date >= $currentDate->toDate();
            });

            if ($activePriceRanges->isNotEmpty()) {
                // Ordenar por prioridad DESC, luego por created_at DESC
                $winnerRange = $activePriceRanges
                    ->sortByDesc(function ($range) {
                        return $range->priceGroup->priority;
                    })
                    ->values()
                    ->first();

                // Si hay múltiples con la misma prioridad, seleccionar el más reciente
                $sameMaxPriority = $activePriceRanges->filter(function ($range) use ($winnerRange) {
                    return $range->priceGroup->priority === $winnerRange->priceGroup->priority;
                });

                if ($sameMaxPriority->count() > 1) {
                    $winnerRange = $sameMaxPriority->sortByDesc('created_at')->first();
                }

                $result[$dayString] = (float) $winnerRange->priceGroup->price_per_night;
            }

            $currentDate->addDay();
        }

        return $result;
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
