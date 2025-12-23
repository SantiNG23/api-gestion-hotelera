<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PriceGroupCompleteRequest;
use App\Http\Requests\PriceGroupRequest;
use App\Http\Resources\PriceGroupResource;
use App\Models\PriceGroup;
use App\Models\CabinPriceByGuests;
use App\Models\PriceRange;
use App\Services\PriceGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceGroupController extends Controller
{
    public function __construct(
        private readonly PriceGroupService $priceGroupService
    ) {}

    /**
     * Filtros permitidos para grupos de precio
     */
    protected function getAllowedFilters(): array
    {
        return ['is_default', 'global'];
    }

    /**
     * Listar grupos de precio
     */
    public function index(Request $request): JsonResponse
    {
        $params = $this->getQueryParams($request);
        $priceGroups = $this->priceGroupService->getPriceGroups($params);

        return $this->paginatedResponse($priceGroups, PriceGroupResource::class);
    }

    /**
     * Crear grupo de precio
     */
    public function store(PriceGroupRequest $request): JsonResponse
    {
        $priceGroup = $this->priceGroupService->createPriceGroup($request->validated());

        return $this->successResponse(
            new PriceGroupResource($priceGroup),
            'Grupo de precio creado exitosamente',
            201
        );
    }

    /**
     * Mostrar grupo de precio
     */
    public function show(int $id): JsonResponse
    {
        $priceGroup = $this->priceGroupService->getPriceGroup($id);

        return $this->successResponse(new PriceGroupResource($priceGroup));
    }

    /**
     * Actualizar grupo de precio
     */
    public function update(PriceGroupRequest $request, int $id): JsonResponse
    {
        $priceGroup = $this->priceGroupService->updatePriceGroup($id, $request->validated());

        return $this->successResponse(
            new PriceGroupResource($priceGroup),
            'Grupo de precio actualizado exitosamente'
        );
    }

    /**
     * Eliminar grupo de precio
     */
    public function destroy(int $id): JsonResponse
    {
        $this->priceGroupService->deletePriceGroup($id);

        return $this->successResponse(null, 'Grupo de precio eliminado exitosamente');
    }

    /**
     * Crear grupo de precio completo (grupo + cabañas + precios + rangos)
     */
    public function storeComplete(PriceGroupCompleteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Validaciones personalizadas
        $this->validateCabinsAndPrices($validated['cabins']);
        
        if (!empty($validated['date_ranges'])) {
            $this->validateDateRanges($validated['date_ranges']);
        }

        try {
            \DB::beginTransaction();

            // 1. Crear el PriceGroup
            $priceGroup = PriceGroup::create([
                'name' => $validated['name'],
                'price_per_night' => 0, // Obsoleto pero obligatorio por el schema
                'priority' => $validated['priority'] ?? 0,
                'is_default' => $validated['is_default'] ?? false,
                'tenant_id' => auth()->user()->tenant_id,
            ]);

            // 2. Crear los precios por cabaña y huéspedes
            foreach ($validated['cabins'] as $cabinData) {
                foreach ($cabinData['prices'] as $priceData) {
                    CabinPriceByGuests::create([
                        'cabin_id' => $cabinData['cabin_id'],
                        'price_group_id' => $priceGroup->id,
                        'num_guests' => $priceData['num_guests'],
                        'price_per_night' => $priceData['price_per_night'],
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            // 3. Crear los rangos de fecha (si existen)
            if (!empty($validated['date_ranges'])) {
                foreach ($validated['date_ranges'] as $rangeData) {
                    PriceRange::create([
                        'price_group_id' => $priceGroup->id,
                        'start_date' => $rangeData['start_date'],
                        'end_date' => $rangeData['end_date'],
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            \DB::commit();

            // 4. Cargar relaciones para la respuesta
            $priceGroup->load([
                'priceRanges',
                'cabinPrices.cabin:id,name,capacity'
            ]);

            // 5. Agregar contadores
            $cabinsCount = $priceGroup->cabinPrices->pluck('cabin_id')->unique()->count();
            $pricesCount = $priceGroup->cabinPrices->count();
            
            $response = $priceGroup->toArray();
            $response['cabins_count'] = $cabinsCount;
            $response['prices_count'] = $pricesCount;

            return $this->successResponse(
                $response,
                'Grupo de precios creado exitosamente',
                201
            );

        } catch (\Exception $e) {
            \DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el grupo de precios',
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Actualizar grupo de precio completo
     */
    public function updateComplete(PriceGroupCompleteRequest $request, int $id): JsonResponse
    {
        // Validar que el grupo existe antes de procesar
        $priceGroup = PriceGroup::where('tenant_id', auth()->user()->tenant_id)
            ->find($id);
        
        if (!$priceGroup) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo de precio no encontrado',
            ], 404);
        }

        $validated = $request->validated();

        \DB::beginTransaction();

        try {
            // 1. Actualizar datos básicos del grupo
            if (isset($validated['name']) || isset($validated['is_default']) || isset($validated['priority'])) {
                $updateData = [];
                
                if (isset($validated['name'])) {
                    $updateData['name'] = $validated['name'];
                }
                
                if (isset($validated['is_default'])) {
                    $updateData['is_default'] = $validated['is_default'];
                }
                
                if (isset($validated['priority'])) {
                    $updateData['priority'] = (int) $validated['priority'];
                }
                
                if (!empty($updateData)) {
                    $priceGroup->update($updateData);
                }
            }

            // 2. Reemplazar precios de cabañas (si se envió)
            if (isset($validated['cabins']) && is_array($validated['cabins']) && count($validated['cabins']) > 0) {
                // Validar cabañas y precios
                $this->validateCabinsAndPrices($validated['cabins']);
                
                // Eliminar precios existentes
                \App\Models\CabinPriceByGuests::where('price_group_id', $priceGroup->id)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->forceDelete();
                
                // Crear nuevos precios
                foreach ($validated['cabins'] as $cabinData) {
                    if (!isset($cabinData['cabin_id']) || !isset($cabinData['prices'])) {
                        throw new \Exception('Estructura de cabañas inválida');
                    }
                    
                    foreach ($cabinData['prices'] as $priceData) {
                        if (!isset($priceData['num_guests']) || !isset($priceData['price_per_night'])) {
                            throw new \Exception('Estructura de precios inválida');
                        }
                        
                        \App\Models\CabinPriceByGuests::create([
                            'cabin_id' => (int) $cabinData['cabin_id'],
                            'price_group_id' => $priceGroup->id,
                            'num_guests' => (int) $priceData['num_guests'],
                            'price_per_night' => (float) $priceData['price_per_night'],
                            'tenant_id' => auth()->user()->tenant_id,
                        ]);
                    }
                }
            }

            // 3. Reemplazar rangos de fecha (si se envió)
            if (isset($validated['date_ranges']) && is_array($validated['date_ranges']) && count($validated['date_ranges']) > 0) {
                $this->validateDateRanges($validated['date_ranges']);
                
                // Eliminar rangos existentes
                \App\Models\PriceRange::where('price_group_id', $priceGroup->id)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->forceDelete();
                
                // Crear nuevos rangos
                foreach ($validated['date_ranges'] as $rangeData) {
                    if (!isset($rangeData['start_date']) || !isset($rangeData['end_date'])) {
                        throw new \Exception('Estructura de rangos de fecha inválida');
                    }
                    
                    \App\Models\PriceRange::create([
                        'price_group_id' => $priceGroup->id,
                        'start_date' => $rangeData['start_date'],
                        'end_date' => $rangeData['end_date'],
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            \DB::commit();

            // Cargar relaciones para la respuesta
            $priceGroup->refresh();
            $priceGroup->load([
                'priceRanges',
                'cabinPrices.cabin:id,name,capacity'
            ]);

            return $this->successResponse(
                new PriceGroupResource($priceGroup),
                'Grupo de precios actualizado exitosamente'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            \DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            \DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el grupo de precios',
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Obtener grupo de precio completo con todas sus relaciones
     */
    public function showComplete(int $id): JsonResponse
    {
        try {
            $priceGroup = \App\Models\PriceGroup::where('tenant_id', auth()->user()->tenant_id)
                ->with([
                    'priceRanges',
                    'cabinPrices.cabin:id,name,description,capacity,is_active'
                ])
                ->find($id);
            
            // Validar que el grupo existe
            if (!$priceGroup) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo de precio no encontrado',
                ], 404);
            }

            // Agrupar precios por cabaña, filtrando cabañas eliminadas
            $cabinsWithPrices = $priceGroup->cabinPrices
                ->groupBy('cabin_id')
                ->filter(function ($prices) {
                    // Filtrar solo los grupos donde la cabaña existe
                    return $prices->first()?->cabin !== null;
                })
                ->map(function ($prices, $cabinId) {
                    $cabin = $prices->first()?->cabin;
                    
                    // Protección adicional en caso de que cabin sea null
                    if (!$cabin) {
                        return null;
                    }
                    
                    return [
                        'id' => $cabin->id,
                        'name' => $cabin->name,
                        'description' => $cabin->description,
                        'capacity' => $cabin->capacity,
                        'is_active' => $cabin->is_active,
                        'prices_in_group' => $prices->map(function ($price) {
                            return [
                                'id' => $price->id,
                                'num_guests' => $price->num_guests,
                                'price_per_night' => $price->price_per_night,
                            ];
                        })->sortBy('num_guests')->values()
                    ];
                })
                ->filter() // Filtrar valores nulos
                ->values();

            // Preparar respuesta
            $response = $priceGroup->toArray();
            $response['cabins'] = $cabinsWithPrices;
            $response['cabins_count'] = $cabinsWithPrices->count();
            $response['prices_count'] = $priceGroup->cabinPrices->count();

            return $this->successResponse($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el grupo de precios',
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Validar que las cabañas y precios sean correctos
     */
    private function validateCabinsAndPrices(array $cabins): void
    {
        $seen = [];
        
        foreach ($cabins as $cabinData) {
            $cabin = \App\Models\Cabin::findOrFail($cabinData['cabin_id']);
            
            // Verificar tenant
            if ($cabin->tenant_id !== auth()->user()->tenant_id) {
                throw new \Exception('La cabaña no pertenece a tu cuenta');
            }
            
            foreach ($cabinData['prices'] as $priceData) {
                // Validar capacidad
                if ($priceData['num_guests'] > $cabin->capacity) {
                    throw new \Exception(
                        "La cantidad de huéspedes ({$priceData['num_guests']}) excede la capacidad de '{$cabin->name}' ({$cabin->capacity})"
                    );
                }
                
                // Validar duplicados
                $key = $cabinData['cabin_id'] . '-' . $priceData['num_guests'];
                if (isset($seen[$key])) {
                    throw new \Exception(
                        "Precio duplicado para {$priceData['num_guests']} huéspedes en '{$cabin->name}'"
                    );
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
        for ($i = 0; $i < count($ranges); $i++) {
            for ($j = $i + 1; $j < count($ranges); $j++) {
                $range1 = $ranges[$i];
                $range2 = $ranges[$j];
                
                $start1 = new \DateTime($range1['start_date']);
                $end1 = new \DateTime($range1['end_date']);
                $start2 = new \DateTime($range2['start_date']);
                $end2 = new \DateTime($range2['end_date']);
                
                // Verificar solapamiento
                if ($start1 <= $end2 && $end1 >= $start2) {
                    throw new \Exception('Los rangos de fecha no pueden solaparse');
                }
            }
        }
    }
}

