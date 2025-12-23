<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PriceGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceRange;
use Illuminate\Validation\ValidationException;

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
        $query = $this->model->query();
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
        return DB::transaction(function () use ($data) {
            // Si se marca como default, desactivar otros defaults del mismo tenant
            if (!empty($data['is_default'])) {
                $tenantId = $data['tenant_id'] ?? Auth::user()->tenant_id;
                $this->model->where('tenant_id', $tenantId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return $this->create($data);
        });
    }

    /**
     * Actualiza un grupo de precio existente
     */
    public function updatePriceGroup(int $id, array $data): PriceGroup
    {
        return DB::transaction(function () use ($id, $data) {
            // Si se marca como default, desactivar otros defaults del mismo tenant
            if (!empty($data['is_default'])) {
                $priceGroup = $this->getById($id);
                $this->model->where('tenant_id', $priceGroup->tenant_id)
                    ->where('id', '!=', $id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return $this->update($id, $data);
        });
    }

    /**
     * Elimina un grupo de precio (eliminación completa / hard delete)
     * Junto con todos sus precios de cabaña y rangos de precio asociados
     */
    public function deletePriceGroup(int $id): bool
    {
        $priceGroup = $this->getById($id);
        
        // Eliminar en cascada antes del hard delete
        $priceGroup->priceRanges()->forceDelete();
        $priceGroup->cabinPricesByGuests()->forceDelete();
        
        // Realizar eliminación completa del grupo
        return (bool) $priceGroup->forceDelete();
    }

    /**
     * Obtiene un grupo de precio completo con todas sus relaciones agrupadas por cabaña
     */
    public function getCompletePriceGroup(int $id): array
    {
        $priceGroup = $this->model->where('tenant_id', Auth::user()->tenant_id)
            ->with([
                'priceRanges',
                'cabinPricesByGuests.cabin:id,name,description,capacity,is_active'
            ])
            ->findOrFail($id);

        // Agrupar precios por cabaña, filtrando cabañas eliminadas
        $cabinsWithPrices = $priceGroup->cabinPricesByGuests
            ->groupBy('cabin_id')
            ->filter(fn($prices) => $prices->first()?->cabin !== null)
            ->map(function ($prices) {
                $cabin = $prices->first()->cabin;
                return [
                    'id' => $cabin->id,
                    'name' => $cabin->name,
                    'description' => $cabin->description,
                    'capacity' => $cabin->capacity,
                    'is_active' => $cabin->is_active,
                    'prices_in_group' => $prices->map(fn($p) => [
                        'id' => $p->id,
                        'num_guests' => $p->num_guests,
                        'price_per_night' => (float) $p->price_per_night,
                    ])->sortBy('num_guests')->values()
                ];
            })
            ->values();

        $priceGroupData = $priceGroup->toArray();
        $priceGroupData['cabins'] = $cabinsWithPrices;
        $priceGroupData['cabins_count'] = $cabinsWithPrices->count();
        $priceGroupData['prices_count'] = $priceGroup->cabinPricesByGuests->count();

        return $priceGroupData;
    }

    /**
     * Crea un grupo de precio completo (grupo + cabañas + precios + rangos)
     */
    public function createCompletePriceGroup(array $data): PriceGroup
    {
        $this->validateCabinsAndPrices($data['cabins']);
        
        if (!empty($data['date_ranges'])) {
            $this->validateDateRanges($data['date_ranges']);
        }

        return DB::transaction(function () use ($data) {
            $tenantId = Auth::user()->tenant_id;

            // 1. Crear el PriceGroup
            $priceGroup = $this->createPriceGroup([
                'name' => $data['name'],
                'price_per_night' => 0,
                'priority' => $data['priority'] ?? 0,
                'is_default' => $data['is_default'] ?? false,
                'tenant_id' => $tenantId,
            ]);

            // 2. Crear los precios por cabaña
            foreach ($data['cabins'] as $cabinData) {
                foreach ($cabinData['prices'] as $priceData) {
                    CabinPriceByGuests::create([
                        'cabin_id' => $cabinData['cabin_id'],
                        'price_group_id' => $priceGroup->id,
                        'num_guests' => $priceData['num_guests'],
                        'price_per_night' => $priceData['price_per_night'],
                        'tenant_id' => $tenantId,
                    ]);
                }
            }

            // 3. Crear los rangos de fecha
            if (!empty($data['date_ranges'])) {
                foreach ($data['date_ranges'] as $rangeData) {
                    PriceRange::create([
                        'price_group_id' => $priceGroup->id,
                        'start_date' => $rangeData['start_date'],
                        'end_date' => $rangeData['end_date'],
                        'tenant_id' => $tenantId,
                    ]);
                }
            }

            return $priceGroup;
        });
    }

    /**
     * Actualiza un grupo de precio completo
     */
    public function updateCompletePriceGroup(int $id, array $data): PriceGroup
    {
        $priceGroup = $this->getById($id);

        if (!empty($data['cabins'])) {
            $this->validateCabinsAndPrices($data['cabins']);
        }
        
        if (!empty($data['date_ranges'])) {
            $this->validateDateRanges($data['date_ranges']);
        }

        return DB::transaction(function () use ($priceGroup, $data) {
            $tenantId = Auth::user()->tenant_id;

            // 1. Actualizar datos básicos
            $this->updatePriceGroup($priceGroup->id, array_intersect_key($data, array_flip(['name', 'is_default', 'priority'])));

            // 2. Reemplazar precios de cabañas
            if (isset($data['cabins'])) {
                $priceGroup->cabinPricesByGuests()->forceDelete();
                foreach ($data['cabins'] as $cabinData) {
                    foreach ($cabinData['prices'] as $priceData) {
                        CabinPriceByGuests::create([
                            'cabin_id' => $cabinData['cabin_id'],
                            'price_group_id' => $priceGroup->id,
                            'num_guests' => $priceData['num_guests'],
                            'price_per_night' => $priceData['price_per_night'],
                            'tenant_id' => $tenantId,
                        ]);
                    }
                }
            }

            // 3. Reemplazar rangos de fecha
            if (isset($data['date_ranges'])) {
                $priceGroup->priceRanges()->forceDelete();
                foreach ($data['date_ranges'] as $rangeData) {
                    PriceRange::create([
                        'price_group_id' => $priceGroup->id,
                        'start_date' => $rangeData['start_date'],
                        'end_date' => $rangeData['end_date'],
                        'tenant_id' => $tenantId,
                    ]);
                }
            }

            return $priceGroup->fresh();
        });
    }

    /**
     * Validar que las cabañas y precios sean correctos
     */
    private function validateCabinsAndPrices(array $cabins): void
    {
        $seen = [];
        $tenantId = Auth::user()->tenant_id;
        
        foreach ($cabins as $cabinData) {
            $cabin = Cabin::findOrFail($cabinData['cabin_id']);
            
            if ($cabin->tenant_id !== $tenantId) {
                throw ValidationException::withMessages([
                    'cabins' => ['La cabaña no pertenece a tu cuenta']
                ]);
            }
            
            foreach ($cabinData['prices'] as $priceData) {
                if ($priceData['num_guests'] > $cabin->capacity) {
                    throw ValidationException::withMessages([
                        'cabins.prices' => ["La cantidad de huéspedes ({$priceData['num_guests']}) excede la capacidad de '{$cabin->name}' ({$cabin->capacity})"]
                    ]);
                }
                
                $key = $cabinData['cabin_id'] . '-' . $priceData['num_guests'];
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        'cabins.prices' => ["Precio duplicado para {$priceData['num_guests']} huéspedes en '{$cabin->name}'"]
                    ]);
                }
                $seen[$key] = true;
            }
        }
    }

    /**
     * Validar que los rangos de fecha no se solapen
     */
    private function validateDateRanges(array $ranges): void
    {
        $count = count($ranges);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $start1 = new \DateTime($ranges[$i]['start_date']);
                $end1 = new \DateTime($ranges[$i]['end_date']);
                $start2 = new \DateTime($ranges[$j]['start_date']);
                $end2 = new \DateTime($ranges[$j]['end_date']);
                
                if ($start1 <= $end2 && $end1 >= $start2) {
                    throw ValidationException::withMessages([
                        'date_ranges' => ['Los rangos de fecha no pueden solaparse']
                    ]);
                }
            }
        }
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['name'];
    }
}
