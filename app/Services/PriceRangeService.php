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
        // Validar y obtener el grupo en una sola operación
        $priceGroup = $this->getPriceGroupOrThrow($data['price_group_id']);

        // Asegurar que el tenant_id coincida con el del grupo
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
        if (isset($data['price_group_id'])) {
            $this->getPriceGroupOrThrow($data['price_group_id']);
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

        // Obtener el grupo por defecto del tenant
        $defaultGroup = PriceGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

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
                    ->sort(function ($a, $b) {
                        if ($a->priceGroup->priority !== $b->priceGroup->priority) {
                            return $b->priceGroup->priority <=> $a->priceGroup->priority;
                        }
                        return $b->created_at <=> $a->created_at;
                    })
                    ->first();

                // Si hay múltiples con la misma prioridad, seleccionar el más reciente
                $sameMaxPriority = $activePriceRanges->filter(function ($range) use ($winnerRange) {
                    return $range->priceGroup->priority === $winnerRange->priceGroup->priority;
                });

                $result[$dayString] = [
                    'price' => (float) $winnerRange->priceGroup->price_per_night,
                    'group_name' => $winnerRange->priceGroup->name,
                ];
            } elseif ($defaultGroup) {
                // Fallback al grupo por defecto
                $result[$dayString] = [
                    'price' => (float) $defaultGroup->price_per_night,
                    'group_name' => $defaultGroup->name,
                ];
            } else {
                // Si no hay nada de nada, precio 0
                $result[$dayString] = [
                    'price' => 0.0,
                    'group_name' => 'Sin tarifa configurada',
                ];
            }

            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Obtiene el grupo de precio validando su existencia y pertenencia al tenant
     *
     * @throws ValidationException
     */
    private function getPriceGroupOrThrow(int $priceGroupId): PriceGroup
    {
        $priceGroup = PriceGroup::find($priceGroupId);

        if (!$priceGroup) {
            throw ValidationException::withMessages([
                'price_group_id' => ['El grupo de precio no existe para este tenant'],
            ]);
        }

        return $priceGroup;
    }

    /**
     * Campo de ordenamiento por defecto
     */
    protected function getDateColumn(): string
    {
        return 'start_date';
    }
}
