# 🔧 Requerimientos Backend - Módulo de Tarifas (Opción B)

## 📋 Resumen Ejecutivo

Necesitamos crear **4 nuevos endpoints** que permitan gestionar grupos de precios de manera unificada, incluyendo cabañas asignadas, precios por cantidad de huéspedes y rangos de fechas, todo en una sola transacción.

---

## 🎯 Endpoints a Crear

### 1. **Crear Grupo de Precio Completo**

```
POST /api/v1/price-groups/complete
```

**Descripción:** Crea un grupo de precio con todas sus relaciones (precios por cabaña/huéspedes y rangos de fecha) en una sola transacción atómica.

#### Request Body

```typescript
{
    name: string; // Requerido, nombre del grupo
    is_default: boolean; // Opcional, default: false
    cabins: Array<{
        // Requerido, mínimo 1 cabaña
        cabin_id: number; // Requerido, debe existir
        prices: Array<{
            // Requerido, mínimo 1 precio
            num_guests: number; // Requerido, >= 1, <= capacidad de la cabaña
            price_per_night: number; // Requerido, >= 0, <= 999999.99
        }>;
    }>;
    date_ranges: Array<{
        // Opcional (puede asignarse después)
        start_date: string; // Requerido si existe el array, formato: Y-m-d
        end_date: string; // Requerido si existe el array, formato: Y-m-d
    }>;
}
```

#### Ejemplo Request

```json
{
    "name": "Temporada Alta",
    "is_default": false,
    "cabins": [
        {
            "cabin_id": 1,
            "prices": [
                {
                    "num_guests": 2,
                    "price_per_night": 15000.0
                },
                {
                    "num_guests": 3,
                    "price_per_night": 18000.0
                },
                {
                    "num_guests": 4,
                    "price_per_night": 20000.0
                }
            ]
        },
        {
            "cabin_id": 2,
            "prices": [
                {
                    "num_guests": 2,
                    "price_per_night": 12000.0
                },
                {
                    "num_guests": 3,
                    "price_per_night": 14000.0
                },
                {
                    "num_guests": 4,
                    "price_per_night": 16000.0
                },
                {
                    "num_guests": 5,
                    "price_per_night": 18000.0
                },
                {
                    "num_guests": 6,
                    "price_per_night": 20000.0
                }
            ]
        }
    ],
    "date_ranges": [
        {
            "start_date": "2025-12-15",
            "end_date": "2026-02-28"
        },
        {
            "start_date": "2025-07-01",
            "end_date": "2025-07-31"
        }
    ]
}
```

#### Response (201 Created)

```json
{
    "success": true,
    "message": "Grupo de precios creado exitosamente",
    "data": {
        "id": 1,
        "name": "Temporada Alta",
        "price_per_night": 0.0,
        "is_default": false,
        "created_at": "2025-12-16T10:30:00.000000Z",
        "updated_at": "2025-12-16T10:30:00.000000Z",
        "price_ranges": [
            {
                "id": 1,
                "price_group_id": 1,
                "start_date": "2025-12-15",
                "end_date": "2026-02-28",
                "created_at": "2025-12-16T10:30:00.000000Z",
                "updated_at": "2025-12-16T10:30:00.000000Z"
            },
            {
                "id": 2,
                "price_group_id": 1,
                "start_date": "2025-07-01",
                "end_date": "2025-07-31",
                "created_at": "2025-12-16T10:30:00.000000Z",
                "updated_at": "2025-12-16T10:30:00.000000Z"
            }
        ],
        "cabin_prices": [
            {
                "id": 1,
                "cabin_id": 1,
                "price_group_id": 1,
                "num_guests": 2,
                "price_per_night": 15000.0,
                "created_at": "2025-12-16T10:30:00.000000Z",
                "updated_at": "2025-12-16T10:30:00.000000Z",
                "cabin": {
                    "id": 1,
                    "name": "Cabaña Deluxe",
                    "capacity": 4
                }
            },
            {
                "id": 2,
                "cabin_id": 1,
                "price_group_id": 1,
                "num_guests": 3,
                "price_per_night": 18000.0,
                "created_at": "2025-12-16T10:30:00.000000Z",
                "updated_at": "2025-12-16T10:30:00.000000Z",
                "cabin": {
                    "id": 1,
                    "name": "Cabaña Deluxe",
                    "capacity": 4
                }
            }
            // ... resto de precios
        ],
        "cabins_count": 2,
        "prices_count": 8
    }
}
```

#### Validaciones Requeridas

```php
// Validaciones del Request
[
    'name' => 'required|string|max:255|unique:price_groups,name,NULL,id,tenant_id,' . auth()->user()->tenant_id,
    'is_default' => 'boolean',
    'cabins' => 'required|array|min:1',
    'cabins.*.cabin_id' => 'required|integer|exists:cabins,id',
    'cabins.*.prices' => 'required|array|min:1',
    'cabins.*.prices.*.num_guests' => 'required|integer|min:1|max:255',
    'cabins.*.prices.*.price_per_night' => 'required|numeric|min:0|max:999999.99',
    'date_ranges' => 'array',
    'date_ranges.*.start_date' => 'required_with:date_ranges|date|date_format:Y-m-d',
    'date_ranges.*.end_date' => 'required_with:date_ranges|date|date_format:Y-m-d|after:date_ranges.*.start_date',
]

// Validaciones adicionales en el Controller
foreach ($request->cabins as $cabinData) {
    $cabin = Cabin::find($cabinData['cabin_id']);

    // Verificar que la cabaña pertenece al tenant
    if ($cabin->tenant_id !== auth()->user()->tenant_id) {
        throw new ValidationException('La cabaña no pertenece a tu cuenta');
    }

    foreach ($cabinData['prices'] as $priceData) {
        // Validar que num_guests no exceda la capacidad
        if ($priceData['num_guests'] > $cabin->capacity) {
            throw new ValidationException(
                "La cantidad de huéspedes ({$priceData['num_guests']}) excede la capacidad de la cabaña '{$cabin->name}' ({$cabin->capacity})"
            );
        }

        // Validar que no existan duplicados en el mismo request
        // (cabin_id + num_guests debe ser único dentro del request)
        $uniqueKey = $cabinData['cabin_id'] . '-' . $priceData['num_guests'];
        if (isset($seen[$uniqueKey])) {
            throw new ValidationException(
                "Precio duplicado para {$priceData['num_guests']} huéspedes en la cabaña '{$cabin->name}'"
            );
        }
        $seen[$uniqueKey] = true;
    }
}

// Validar solapamiento de rangos de fecha
if (!empty($request->date_ranges)) {
    foreach ($request->date_ranges as $i => $range1) {
        foreach ($request->date_ranges as $j => $range2) {
            if ($i >= $j) continue;

            if (dateRangesOverlap($range1, $range2)) {
                throw new ValidationException(
                    'Los rangos de fecha no pueden solaparse'
                );
            }
        }
    }
}
```

#### Implementación en Laravel (PHP)

```php
// app/Http/Controllers/Api/PriceGroupController.php

use Illuminate\Support\Facades\DB;

public function storeComplete(Request $request)
{
    // Validar request (ver validaciones arriba)
    $validated = $request->validate([
        'name' => 'required|string|max:255|unique:price_groups,name,NULL,id,tenant_id,' . auth()->user()->tenant_id,
        'is_default' => 'boolean',
        'cabins' => 'required|array|min:1',
        'cabins.*.cabin_id' => 'required|integer|exists:cabins,id',
        'cabins.*.prices' => 'required|array|min:1',
        'cabins.*.prices.*.num_guests' => 'required|integer|min:1|max:255',
        'cabins.*.prices.*.price_per_night' => 'required|numeric|min:0|max:999999.99',
        'date_ranges' => 'array',
        'date_ranges.*.start_date' => 'required_with:date_ranges|date|date_format:Y-m-d',
        'date_ranges.*.end_date' => 'required_with:date_ranges|date|date_format:Y-m-d|after:date_ranges.*.start_date',
    ]);

    // Validaciones personalizadas (capacidad, duplicados, etc)
    $this->validateCabinsAndPrices($validated['cabins']);

    if (!empty($validated['date_ranges'])) {
        $this->validateDateRanges($validated['date_ranges']);
    }

    try {
        DB::beginTransaction();

        // 1. Crear el PriceGroup
        $priceGroup = PriceGroup::create([
            'name' => $validated['name'],
            'price_per_night' => 0, // Obsoleto pero obligatorio por el schema
            'is_default' => $validated['is_default'] ?? false,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        // 2. Crear los precios por cabaña y huéspedes
        $cabinPrices = [];
        foreach ($validated['cabins'] as $cabinData) {
            foreach ($cabinData['prices'] as $priceData) {
                $cabinPrice = CabinPriceByGuests::create([
                    'cabin_id' => $cabinData['cabin_id'],
                    'price_group_id' => $priceGroup->id,
                    'num_guests' => $priceData['num_guests'],
                    'price_per_night' => $priceData['price_per_night'],
                    'tenant_id' => auth()->user()->tenant_id,
                ]);

                $cabinPrices[] = $cabinPrice;
            }
        }

        // 3. Crear los rangos de fecha (si existen)
        $priceRanges = [];
        if (!empty($validated['date_ranges'])) {
            foreach ($validated['date_ranges'] as $rangeData) {
                $priceRange = PriceRange::create([
                    'price_group_id' => $priceGroup->id,
                    'start_date' => $rangeData['start_date'],
                    'end_date' => $rangeData['end_date'],
                    'tenant_id' => auth()->user()->tenant_id,
                ]);

                $priceRanges[] = $priceRange;
            }
        }

        DB::commit();

        // 4. Cargar relaciones para la respuesta
        $priceGroup->load([
            'priceRanges',
            'cabinPrices.cabin:id,name,capacity'
        ]);

        // 5. Agregar contadores
        $priceGroup->cabins_count = $priceGroup->cabinPrices->pluck('cabin_id')->unique()->count();
        $priceGroup->prices_count = $priceGroup->cabinPrices->count();

        return response()->json([
            'success' => true,
            'message' => 'Grupo de precios creado exitosamente',
            'data' => $priceGroup
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Error al crear el grupo de precios',
            'errors' => ['server' => [$e->getMessage()]]
        ], 500);
    }
}

// Método auxiliar para validar cabañas y precios
private function validateCabinsAndPrices(array $cabins)
{
    $seen = [];

    foreach ($cabins as $cabinData) {
        $cabin = Cabin::findOrFail($cabinData['cabin_id']);

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

// Método auxiliar para validar solapamiento de fechas
private function validateDateRanges(array $ranges)
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
                throw new \Exception(
                    'Los rangos de fecha no pueden solaparse'
                );
            }
        }
    }
}
```

---

### 2. **Actualizar Grupo de Precio Completo**

```
PUT /api/v1/price-groups/{id}/complete
```

**Descripción:** Actualiza un grupo de precio y REEMPLAZA completamente sus precios por cabaña y rangos de fecha.

#### Request Body

Mismo formato que el POST, pero todos los campos son opcionales excepto los arrays que, si se envían, reemplazan completamente los existentes.

#### Ejemplo Request

```json
{
    "name": "Temporada Alta Actualizada",
    "cabins": [
        {
            "cabin_id": 1,
            "prices": [
                {
                    "num_guests": 2,
                    "price_per_night": 16000.0
                },
                {
                    "num_guests": 3,
                    "price_per_night": 19000.0
                }
            ]
        }
    ],
    "date_ranges": [
        {
            "start_date": "2025-12-20",
            "end_date": "2026-03-01"
        }
    ]
}
```

#### Comportamiento

-   Si se envía `cabins`: **ELIMINA** todos los precios existentes y crea los nuevos
-   Si se envía `date_ranges`: **ELIMINA** todos los rangos existentes y crea los nuevos
-   Si NO se envía alguno de estos arrays, NO se modifica

#### Implementación Laravel

```php
public function updateComplete(Request $request, $id)
{
    $priceGroup = PriceGroup::where('tenant_id', auth()->user()->tenant_id)
        ->findOrFail($id);

    $validated = $request->validate([
        'name' => 'string|max:255|unique:price_groups,name,' . $id . ',id,tenant_id,' . auth()->user()->tenant_id,
        'is_default' => 'boolean',
        'cabins' => 'array|min:1',
        // ... resto de validaciones
    ]);

    DB::beginTransaction();

    try {
        // 1. Actualizar datos básicos del grupo
        if (isset($validated['name']) || isset($validated['is_default'])) {
            $priceGroup->update([
                'name' => $validated['name'] ?? $priceGroup->name,
                'is_default' => $validated['is_default'] ?? $priceGroup->is_default,
            ]);
        }

        // 2. Reemplazar precios de cabañas (si se envió)
        if (isset($validated['cabins'])) {
            $this->validateCabinsAndPrices($validated['cabins']);

            // Eliminar precios existentes
            CabinPriceByGuests::where('price_group_id', $priceGroup->id)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->delete();

            // Crear nuevos precios
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
        }

        // 3. Reemplazar rangos de fecha (si se envió)
        if (isset($validated['date_ranges'])) {
            $this->validateDateRanges($validated['date_ranges']);

            // Eliminar rangos existentes
            PriceRange::where('price_group_id', $priceGroup->id)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->delete();

            // Crear nuevos rangos
            foreach ($validated['date_ranges'] as $rangeData) {
                PriceRange::create([
                    'price_group_id' => $priceGroup->id,
                    'start_date' => $rangeData['start_date'],
                    'end_date' => $rangeData['end_date'],
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }
        }

        DB::commit();

        // Cargar relaciones
        $priceGroup->load([
            'priceRanges',
            'cabinPrices.cabin:id,name,capacity'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Grupo de precios actualizado exitosamente',
            'data' => $priceGroup
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar el grupo de precios',
            'errors' => ['server' => [$e->getMessage()]]
        ], 500);
    }
}
```

---

### 3. **Obtener Grupo de Precio Completo**

```
GET /api/v1/price-groups/{id}/complete
```

**Descripción:** Obtiene un grupo de precio con TODAS sus relaciones (rangos de fecha, precios por cabaña, información de cabañas).

#### Response (200 OK)

```json
{
    "success": true,
    "message": "Operación exitosa",
    "data": {
        "id": 1,
        "name": "Temporada Alta",
        "price_per_night": 0.0,
        "is_default": false,
        "created_at": "2025-12-16T10:30:00.000000Z",
        "updated_at": "2025-12-16T10:30:00.000000Z",
        "price_ranges": [
            {
                "id": 1,
                "price_group_id": 1,
                "start_date": "2025-12-15",
                "end_date": "2026-02-28"
            }
        ],
        "cabin_prices": [
            {
                "id": 1,
                "cabin_id": 1,
                "price_group_id": 1,
                "num_guests": 2,
                "price_per_night": 15000.0,
                "cabin": {
                    "id": 1,
                    "name": "Cabaña Deluxe",
                    "description": "Cabaña premium con vista al lago",
                    "capacity": 4,
                    "is_active": true
                }
            },
            {
                "id": 2,
                "cabin_id": 1,
                "price_group_id": 1,
                "num_guests": 3,
                "price_per_night": 18000.0,
                "cabin": {
                    "id": 1,
                    "name": "Cabaña Deluxe",
                    "description": "Cabaña premium con vista al lago",
                    "capacity": 4,
                    "is_active": true
                }
            }
            // ... resto de precios
        ],
        "cabins": [
            {
                "id": 1,
                "name": "Cabaña Deluxe",
                "capacity": 4,
                "prices_in_group": [
                    {
                        "num_guests": 2,
                        "price_per_night": 15000.0
                    },
                    {
                        "num_guests": 3,
                        "price_per_night": 18000.0
                    },
                    {
                        "num_guests": 4,
                        "price_per_night": 20000.0
                    }
                ]
            },
            {
                "id": 2,
                "name": "Cabaña Standard",
                "capacity": 6,
                "prices_in_group": [
                    {
                        "num_guests": 2,
                        "price_per_night": 12000.0
                    },
                    {
                        "num_guests": 3,
                        "price_per_night": 14000.0
                    }
                ]
            }
        ],
        "cabins_count": 2,
        "prices_count": 5
    }
}
```

#### Implementación Laravel

```php
public function showComplete($id)
{
    $priceGroup = PriceGroup::where('tenant_id', auth()->user()->tenant_id)
        ->with([
            'priceRanges',
            'cabinPrices.cabin:id,name,description,capacity,is_active'
        ])
        ->findOrFail($id);

    // Agrupar precios por cabaña
    $cabinsWithPrices = $priceGroup->cabinPrices->groupBy('cabin_id')->map(function ($prices, $cabinId) {
        $cabin = $prices->first()->cabin;

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
    })->values();

    // Agregar información adicional
    $priceGroup->cabins = $cabinsWithPrices;
    $priceGroup->cabins_count = $cabinsWithPrices->count();
    $priceGroup->prices_count = $priceGroup->cabinPrices->count();

    return response()->json([
        'success' => true,
        'message' => 'Operación exitosa',
        'data' => $priceGroup
    ]);
}
```

---

### 4. **Calcular Precio de Reserva**

```
POST /api/v1/reservations/calculate-price
```

**Descripción:** Calcula el precio total de una reserva considerando cabaña, fechas, cantidad de huéspedes y grupos de precio aplicables.

#### Request Body

```typescript
{
    cabin_id: number; // Requerido
    check_in_date: string; // Requerido, formato: Y-m-d
    check_out_date: string; // Requerido, formato: Y-m-d
    num_guests: number; // Requerido
}
```

#### Ejemplo Request

```json
{
    "cabin_id": 1,
    "check_in_date": "2025-12-20",
    "check_out_date": "2025-12-25",
    "num_guests": 3
}
```

#### Response (200 OK)

```json
{
    "success": true,
    "message": "Operación exitosa",
    "data": {
        "cabin_id": 1,
        "cabin_name": "Cabaña Deluxe",
        "check_in_date": "2025-12-20",
        "check_out_date": "2025-12-25",
        "num_guests": 3,
        "nights": 5,
        "price_per_night": 18000.0,
        "total_price": 90000.0,
        "deposit_amount": 27000.0,
        "balance_amount": 63000.0,
        "pricing_breakdown": [
            {
                "date": "2025-12-20",
                "price": 18000.0,
                "price_group_id": 1,
                "price_group_name": "Temporada Alta"
            },
            {
                "date": "2025-12-21",
                "price": 18000.0,
                "price_group_id": 1,
                "price_group_name": "Temporada Alta"
            },
            {
                "date": "2025-12-22",
                "price": 18000.0,
                "price_group_id": 1,
                "price_group_name": "Temporada Alta"
            },
            {
                "date": "2025-12-23",
                "price": 18000.0,
                "price_group_id": 1,
                "price_group_name": "Temporada Alta"
            },
            {
                "date": "2025-12-24",
                "price": 18000.0,
                "price_group_id": 1,
                "price_group_name": "Temporada Alta"
            }
        ]
    }
}
```

#### Implementación Laravel

```php
public function calculatePrice(Request $request)
{
    $validated = $request->validate([
        'cabin_id' => 'required|integer|exists:cabins,id',
        'check_in_date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
        'check_out_date' => 'required|date|date_format:Y-m-d|after:check_in_date',
        'num_guests' => 'required|integer|min:1|max:255',
    ]);

    $cabin = Cabin::where('tenant_id', auth()->user()->tenant_id)
        ->findOrFail($validated['cabin_id']);

    // Validar que num_guests no exceda la capacidad
    if ($validated['num_guests'] > $cabin->capacity) {
        return response()->json([
            'success' => false,
            'message' => 'La cantidad de huéspedes excede la capacidad de la cabaña',
            'errors' => [
                'num_guests' => [
                    "La cabaña '{$cabin->name}' tiene capacidad para {$cabin->capacity} personas máximo"
                ]
            ]
        ], 422);
    }

    $checkIn = new \DateTime($validated['check_in_date']);
    $checkOut = new \DateTime($validated['check_out_date']);
    $nights = $checkIn->diff($checkOut)->days;

    // Calcular precio por noche para cada día
    $pricingBreakdown = [];
    $totalPrice = 0;
    $currentDate = clone $checkIn;

    for ($i = 0; $i < $nights; $i++) {
        $dateStr = $currentDate->format('Y-m-d');

        // Buscar el precio aplicable para esta fecha
        $priceInfo = $this->getPriceForDate(
            $cabin->id,
            $dateStr,
            $validated['num_guests']
        );

        $pricingBreakdown[] = [
            'date' => $dateStr,
            'price' => $priceInfo['price'],
            'price_group_id' => $priceInfo['price_group_id'],
            'price_group_name' => $priceInfo['price_group_name'],
        ];

        $totalPrice += $priceInfo['price'];
        $currentDate->modify('+1 day');
    }

    // Calcular seña y saldo (30% - 70%)
    $depositAmount = round($totalPrice * 0.30, 2);
    $balanceAmount = round($totalPrice - $depositAmount, 2);

    return response()->json([
        'success' => true,
        'message' => 'Operación exitosa',
        'data' => [
            'cabin_id' => $cabin->id,
            'cabin_name' => $cabin->name,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'num_guests' => $validated['num_guests'],
            'nights' => $nights,
            'price_per_night' => count($pricingBreakdown) > 0 ? $pricingBreakdown[0]['price'] : 0,
            'total_price' => $totalPrice,
            'deposit_amount' => $depositAmount,
            'balance_amount' => $balanceAmount,
            'pricing_breakdown' => $pricingBreakdown,
        ]
    ]);
}

// Método auxiliar para obtener el precio de una fecha específica
private function getPriceForDate($cabinId, $date, $numGuests)
{
    // 1. Buscar rangos de precio que contengan esta fecha
    $priceRange = PriceRange::where('tenant_id', auth()->user()->tenant_id)
        ->where('start_date', '<=', $date)
        ->where('end_date', '>=', $date)
        ->with('priceGroup')
        ->first();

    // 2. Si existe un rango, buscar el precio específico
    if ($priceRange) {
        $cabinPrice = CabinPriceByGuests::where('tenant_id', auth()->user()->tenant_id)
            ->where('cabin_id', $cabinId)
            ->where('price_group_id', $priceRange->price_group_id)
            ->where('num_guests', $numGuests)
            ->first();

        if ($cabinPrice) {
            return [
                'price' => $cabinPrice->price_per_night,
                'price_group_id' => $priceRange->price_group_id,
                'price_group_name' => $priceRange->priceGroup->name,
            ];
        }
    }

    // 3. Si no hay rango, buscar el grupo por defecto
    $defaultGroup = PriceGroup::where('tenant_id', auth()->user()->tenant_id)
        ->where('is_default', true)
        ->first();

    if ($defaultGroup) {
        $cabinPrice = CabinPriceByGuests::where('tenant_id', auth()->user()->tenant_id)
            ->where('cabin_id', $cabinId)
            ->where('price_group_id', $defaultGroup->id)
            ->where('num_guests', $numGuests)
            ->first();

        if ($cabinPrice) {
            return [
                'price' => $cabinPrice->price_per_night,
                'price_group_id' => $defaultGroup->id,
                'price_group_name' => $defaultGroup->name,
            ];
        }
    }

    // 4. Si no se encontró ningún precio, lanzar error
    throw new \Exception(
        "No se encontró un precio configurado para {$numGuests} huéspedes en la fecha {$date}"
    );
}
```

---

## 🔧 Modificaciones a Modelos Existentes

### 1. **Modelo PriceGroup**

Agregar relación a `CabinPriceByGuests`:

```php
// app/Models/PriceGroup.php

public function cabinPrices()
{
    return $this->hasMany(CabinPriceByGuests::class, 'price_group_id');
}

// Accessor para obtener las cabañas únicas
public function getCabinsAttribute()
{
    return $this->cabinPrices()
        ->with('cabin:id,name,capacity')
        ->get()
        ->groupBy('cabin_id')
        ->map(function ($prices) {
            return $prices->first()->cabin;
        })
        ->values();
}
```

### 2. **Modelo CabinPriceByGuests**

Asegurar que tenga las relaciones necesarias:

```php
// app/Models/CabinPriceByGuests.php

public function cabin()
{
    return $this->belongsTo(Cabin::class);
}

public function priceGroup()
{
    return $this->belongsTo(PriceGroup::class);
}

// Scope para filtrar por grupo y cabaña
public function scopeForGroupAndCabin($query, $priceGroupId, $cabinId)
{
    return $query->where('price_group_id', $priceGroupId)
                 ->where('cabin_id', $cabinId);
}
```

---

## 📝 Rutas a Agregar

```php
// routes/api.php

Route::middleware(['auth:sanctum'])->group(function () {
    // Endpoints nuevos de grupos de precio completos
    Route::post('price-groups/complete', [PriceGroupController::class, 'storeComplete']);
    Route::put('price-groups/{id}/complete', [PriceGroupController::class, 'updateComplete']);
    Route::get('price-groups/{id}/complete', [PriceGroupController::class, 'showComplete']);

    // Endpoint de cálculo de precio
    Route::post('reservations/calculate-price', [ReservationController::class, 'calculatePrice']);
});
```

---

## ✅ Checklist de Implementación

-   [ ] Crear método `storeComplete()` en `PriceGroupController`
-   [ ] Crear método `updateComplete()` en `PriceGroupController`
-   [ ] Crear método `showComplete()` en `PriceGroupController`
-   [ ] Crear método `calculatePrice()` en `ReservationController`
-   [ ] Agregar relación `cabinPrices()` en modelo `PriceGroup`
-   [ ] Agregar método auxiliar `validateCabinsAndPrices()` en `PriceGroupController`
-   [ ] Agregar método auxiliar `validateDateRanges()` en `PriceGroupController`
-   [ ] Agregar método auxiliar `getPriceForDate()` en `ReservationController`
-   [ ] Agregar rutas en `routes/api.php`
-   [ ] Probar con Postman/Insomnia
-   [ ] Documentar en Swagger/OpenAPI (opcional)

---

## 🧪 Casos de Prueba

### Test 1: Crear grupo completo exitoso

```bash
POST /api/v1/price-groups/complete
Authorization: Bearer {token}

{
  "name": "Temporada Alta",
  "cabins": [
    {
      "cabin_id": 1,
      "prices": [
        { "num_guests": 2, "price_per_night": 15000 }
      ]
    }
  ],
  "date_ranges": [
    { "start_date": "2025-12-15", "end_date": "2026-02-28" }
  ]
}
```

**Esperado:** 201 Created

### Test 2: Error - num_guests excede capacidad

```bash
POST /api/v1/price-groups/complete

{
  "name": "Test",
  "cabins": [
    {
      "cabin_id": 1,
      "prices": [
        { "num_guests": 10, "price_per_night": 15000 }
      ]
    }
  ]
}
```

**Esperado:** 422 con error "excede la capacidad"

### Test 3: Calcular precio con múltiples grupos

```bash
POST /api/v1/reservations/calculate-price

{
  "cabin_id": 1,
  "check_in_date": "2025-12-20",
  "check_out_date": "2025-12-25",
  "num_guests": 3
}
```

**Esperado:** 200 con breakdown de precios por día

---

## 📞 Contacto

Si hay dudas o necesitas aclaraciones sobre algún endpoint, por favor consultar con el equipo de frontend.

**Prioridad:** Alta  
**Estimación:** 4-6 horas de desarrollo + 2 horas de testing
