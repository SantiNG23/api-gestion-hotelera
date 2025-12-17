# ✅ Respuestas a Preguntas del Backend - Módulo de Tarifas

---

## 1️⃣ Estructura del Endpoint `/api/v1/price-groups/:id/complete`

### Respuesta Real cuando se obtiene un grupo para editar

El endpoint `GET /api/v1/price-groups/{id}/complete` devuelve la estructura exacta siguiente:

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 1,
    "name": "Temporada Alta",
    "price_per_night": 0.0,
    "priority": 20,
    "is_default": false,
    "created_at": "2025-12-16T10:30:00.000000Z",
    "updated_at": "2025-12-16T10:30:00.000000Z",
    "deleted_at": null,
    "cabins": [
      {
        "id": 1,
        "name": "Cabaña Deluxe",
        "description": "Cabaña de lujo con vista al mar",
        "capacity": 4,
        "is_active": true,
        "prices_in_group": [
          {
            "id": 5,
            "num_guests": 2,
            "price_per_night": 150000.0
          },
          {
            "id": 6,
            "num_guests": 3,
            "price_per_night": 180000.0
          },
          {
            "id": 7,
            "num_guests": 4,
            "price_per_night": 210000.0
          }
        ]
      },
      {
        "id": 2,
        "name": "Cabaña Estándar",
        "description": "Cabaña confortable",
        "capacity": 6,
        "is_active": true,
        "prices_in_group": [
          {
            "id": 8,
            "num_guests": 2,
            "price_per_night": 120000.0
          },
          {
            "id": 9,
            "num_guests": 3,
            "price_per_night": 140000.0
          },
          {
            "id": 10,
            "num_guests": 4,
            "price_per_night": 160000.0
          },
          {
            "id": 11,
            "num_guests": 5,
            "price_per_night": 180000.0
          },
          {
            "id": 12,
            "num_guests": 6,
            "price_per_night": 200000.0
          }
        ]
      }
    ],
    "price_ranges": [
      {
        "id": 1,
        "price_group_id": 1,
        "start_date": "2025-12-20",
        "end_date": "2026-01-10",
        "created_at": "2025-12-16T10:30:00.000000Z",
        "updated_at": "2025-12-16T10:30:00.000000Z",
        "deleted_at": null
      },
      {
        "id": 2,
        "price_group_id": 1,
        "start_date": "2025-07-01",
        "end_date": "2025-07-31",
        "created_at": "2025-12-16T10:30:00.000000Z",
        "updated_at": "2025-12-16T10:30:00.000000Z",
        "deleted_at": null
      }
    ],
    "cabins_count": 2,
    "prices_count": 8
  }
}
```

### ✅ Confirmaciones

- ✅ **Incluye información de todas las cabañas asociadas**: Sí, en el array `cabins`
- ✅ **Incluye los precios por huésped para cada cabaña**: Sí, en `prices_in_group` dentro de cada cabaña
- ✅ **Incluye los rangos de fechas**: Sí, en el array `price_ranges`
- ✅ **Los precios están ordenados**: Sí, por `num_guests` (menor a mayor)

---

## 2️⃣ ¿Qué tabla almacena los precios por huésped?

### Tabla: `cabin_price_by_guests`

**Estructura de la tabla:**

```sql
CREATE TABLE cabin_price_by_guests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    cabin_id BIGINT NOT NULL,
    price_group_id BIGINT NOT NULL,
    num_guests TINYINT UNSIGNED NOT NULL,
    price_per_night DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    -- Índices
    UNIQUE KEY unique_cabin_guest_price (tenant_id, cabin_id, price_group_id, num_guests),
    KEY idx_tenant_cabin (tenant_id, cabin_id),
    KEY idx_price_group (price_group_id)
);
```

### ✅ Confirmaciones

- ✅ **Existe una tabla `cabin_price_by_guests`**: Sí, definida en la migración
- ✅ **¿Cómo se relaciona con price_groups?**: A través de `price_group_id` (Foreign Key)
- ✅ **¿Se devuelve en el endpoint /complete?**: Sí, agrupada en el campo `cabins` > `prices_in_group`

### Relaciones en Modelos

```php
// En PriceGroup.php
public function cabinPrices(): HasMany
{
    return $this->hasMany(CabinPriceByGuests::class);
}

// En CabinPriceByGuests.php
public function priceGroup(): BelongsTo
{
    return $this->belongsTo(PriceGroup::class);
}

public function cabin(): BelongsTo
{
    return $this->belongsTo(Cabin::class);
}
```

---

## 3️⃣ Validaciones del Backend

### 3.1 ¿Valida nombres duplicados al actualizar?

**SÍ**, pero con una excepción importante:

```php
// En updateComplete() - Línea 180
'name' => 'string|max:255|unique:price_groups,name,' . $id . ',id,tenant_id,' . auth()->user()->tenant_id,
```

- ✅ Valida que el nombre sea único **por tenant**
- ✅ Permite que el mismo nombre exista en otros tenants
- ⚠️ **Permite el mismo nombre durante edición**: Sí, porque excluye el `$id` actual

### 3.2 ¿Permite actualizar un grupo sin cambiar su nombre?

**SÍ**, todos los campos son opcionales en `updateComplete()`:

```php
$validated = $request->validate([
    'name' => 'string|max:255|unique:price_groups,name,' . $id . ',id,...', // NO required
    'is_default' => 'boolean',  // NO required
    'cabins' => 'array|min:1',  // NO required
    'date_ranges' => 'array',   // NO required
]);

// Luego, en el controlador:
if (isset($validated['name']) || isset($validated['is_default'])) {
    $priceGroup->update([
        'name' => $validated['name'] ?? $priceGroup->name,
        'is_default' => $validated['is_default'] ?? $priceGroup->is_default,
    ]);
}
```

- ✅ Puedes actualizar cabañas sin cambiar el nombre
- ✅ Puedes actualizar rangos sin cambiar nada más
- ✅ Puedes actualizar parcialmente

### 3.3 ¿Acepta `date_ranges` como `undefined` o debe ser siempre un array?

**Puede ser undefined/null**, no es requerido:

```php
'date_ranges' => 'array',  // Sin 'required'

// En el controlador:
if (isset($validated['date_ranges'])) {
    // Solo procesa si se envió
    $this->validateDateRanges($validated['date_ranges']);
}
```

- ✅ `date_ranges` es completamente opcional
- ✅ Si se envía, debe ser un array
- ✅ Si se envía vacío `[]`, se eliminarán todos los rangos existentes
- ✅ Si no se envía, no se modifica nada

---

## 4️⃣ Estructura de Respuesta en Creación vs Edición

### 4.1 POST `/api/v1/price-groups/complete` (Crear)

```php
public function storeComplete(Request $request): JsonResponse
{
    // ... validación ...
    
    // Respuesta exitosa (201):
    return $this->successResponse(
        $response,
        'Grupo de precios creado exitosamente',
        201
    );
}
```

**Estructura devuelta:**

```json
{
  "success": true,
  "message": "Grupo de precios creado exitosamente",
  "data": {
    // Todos los campos del grupo creado
    "id": 1,
    "name": "Temporada Alta",
    "price_per_night": 0.0,
    "priority": 0,
    "is_default": false,
    "created_at": "2025-12-16T...",
    "updated_at": "2025-12-16T...",
    "deleted_at": null,
    // Nota: La respuesta NO incluye cabins ni price_ranges aquí
    // Solo incluye contadores
    "cabins_count": 2,
    "prices_count": 8
  }
}
```

### 4.2 PUT `/api/v1/price-groups/:id/complete` (Actualizar)

```php
public function updateComplete(Request $request, int $id): JsonResponse
{
    // ... validación y actualización ...
    
    return $this->successResponse(
        new PriceGroupResource($priceGroup),
        'Grupo de precios actualizado exitosamente'
    );
}
```

**Estructura devuelta:**

```json
{
  "success": true,
  "message": "Grupo de precios actualizado exitosamente",
  "data": {
    "id": 1,
    "name": "Temporada Alta",
    "price_per_night": 0.0,
    "priority": 20,
    "is_default": false,
    "price_ranges": [
      {
        "id": 1,
        "price_group_id": 1,
        "start_date": "2025-12-20",
        "end_date": "2026-01-10"
      }
    ]
  }
}
```

### ⚠️ DIFERENCIAS IMPORTANTES

| Aspecto | POST `/complete` | PUT `/:id/complete` |
|---------|------------------|-------------------|
| Status | 201 | 200 |
| Cabins incluidas | ❌ No | ❌ No |
| Price Ranges | Contadores | Sí (via Resource) |
| Precios detalles | ❌ No | ❌ No |
| Para obtener detalles | GET `/complete` | GET `/complete` |

---

## 5️⃣ Comportamiento Esperado al Editar

### 5.1 ¿Se pueden cambiar las cabañas asociadas?

**SÍ**, se reemplaza completamente:

```php
// En updateComplete() - Línea 215
if (isset($validated['cabins'])) {
    // Eliminar todos los precios existentes
    \App\Models\CabinPriceByGuests::where('price_group_id', $priceGroup->id)
        ->where('tenant_id', auth()->user()->tenant_id)
        ->delete();
    
    // Crear nuevos
    foreach ($validated['cabins'] as $cabinData) {
        foreach ($cabinData['prices'] as $priceData) {
            \App\Models\CabinPriceByGuests::create([...]);
        }
    }
}
```

- ✅ Se pueden eliminar cabañas antiguas y agregar nuevas
- ✅ Se elimina todo y se reemplaza (no es merge)
- ⚠️ Si una cabaña tenía precios, se pierden al reemplazar

### 5.2 ¿Qué sucede si se elimina una cabaña que ya tenía precios registrados?

**La relación en cascada elimina los precios:**

```sql
FOREIGN KEY (cabin_id) REFERENCES cabins(id) ON DELETE CASCADE
```

- ✅ Si eliminas una cabaña, se eliminan automáticamente sus precios
- ✅ Si reemplazas las cabañas, los precios antiguos se borran

### 5.3 ¿Se pueden modificar los precios por huésped?

**SÍ**, como parte del reemplazo de cabañas:

```php
// Envía cabañas con precios actualizados
PUT /api/v1/price-groups/1/complete

{
  "cabins": [
    {
      "cabin_id": 1,
      "prices": [
        { "num_guests": 2, "price_per_night": 160000 },  // Actualizado
        { "num_guests": 3, "price_per_night": 190000 }   // Actualizado
      ]
    }
  ]
}
```

---

## 📋 Resumen de Campos Requeridos y Opcionales

### POST `/api/v1/price-groups/complete`

| Campo | Requerido | Tipo | Validación |
|-------|-----------|------|-----------|
| `name` | ✅ Sí | string | max:255, unique por tenant |
| `is_default` | ❌ No | boolean | default: false |
| `cabins` | ✅ Sí | array | min:1 cabañas |
| `cabins.*.cabin_id` | ✅ Sí | integer | must exist in cabins |
| `cabins.*.prices` | ✅ Sí | array | min:1 precio |
| `cabins.*.prices.*.num_guests` | ✅ Sí | integer | 1-255, ≤ capacidad |
| `cabins.*.prices.*.price_per_night` | ✅ Sí | numeric | 0-999999.99 |
| `date_ranges` | ❌ No | array | (opcional) |
| `date_ranges.*.start_date` | ⚠️ Si enviado | string | Y-m-d |
| `date_ranges.*.end_date` | ⚠️ Si enviado | string | Y-m-d, after:start_date |

### PUT `/api/v1/price-groups/:id/complete`

**Todos los campos son opcionales:**

| Campo | Requerido | Tipo | Validación |
|-------|-----------|------|-----------|
| `name` | ❌ No | string | max:255, unique por tenant |
| `is_default` | ❌ No | boolean | - |
| `cabins` | ❌ No | array | min:1 si enviado |
| `cabins.*` | ⚠️ Si enviado | object | igual validación que POST |
| `date_ranges` | ❌ No | array | (opcional) |

---

## 🔍 Validaciones Especiales

### Validación de Capacidad de Cabaña

```php
private function validateCabinsAndPrices(array $cabins): void
{
    foreach ($cabins as $cabinData) {
        $cabin = \App\Models\Cabin::findOrFail($cabinData['cabin_id']);
        
        foreach ($cabinData['prices'] as $priceData) {
            // num_guests NO puede exceder la capacidad de la cabaña
            if ($priceData['num_guests'] > $cabin->capacity) {
                throw new \Exception(
                    "La cantidad de huéspedes ({$priceData['num_guests']}) excede la capacidad"
                );
            }
        }
    }
}
```

### Validación de Duplicados

```php
// No se pueden tener 2 precios para la MISMA cabaña y cantidad de huéspedes
$key = $cabinData['cabin_id'] . '-' . $priceData['num_guests'];
if (isset($seen[$key])) {
    throw new \Exception("Precio duplicado para X huéspedes");
}
```

### Validación de Rangos de Fecha

```php
private function validateDateRanges(array $ranges): void
{
    // Los rangos NO pueden solaparse
    for ($i = 0; $i < count($ranges); $i++) {
        for ($j = $i + 1; $j < count($ranges); $j++) {
            if ($start1 <= $end2 && $end1 >= $start2) {
                throw new \Exception('Los rangos de fecha no pueden solaparse');
            }
        }
    }
}
```

---

## 🎯 Recomendaciones para el Frontend

### 1. **Estructura de Formulario para Edición**

```javascript
// Cuando editas, obtén primero el grupo completo:
GET /api/v1/price-groups/{id}/complete

// Luego envía SOLO lo que cambió:
PUT /api/v1/price-groups/{id}/complete
{
  "name": "Nuevo nombre",  // opcional
  "cabins": [...],         // opcional
  "date_ranges": [...]     // opcional
}
```

### 2. **Manejo de Arrays Vacíos**

```javascript
// ✅ Si quieres eliminar todos los rangos de fecha:
PUT /api/v1/price-groups/{id}/complete
{
  "date_ranges": []  // Elimina todos los rangos
}

// ✅ Si no quieres modificar rangos, no los envíes:
PUT /api/v1/price-groups/{id}/complete
{
  "name": "Nuevo nombre"  // Solo actualiza nombre
}

// ❌ No envíes undefined en JSON
```

### 3. **Flujo de Edición Recomendado**

1. Usuario abre formulario de edición
2. GET `/api/v1/price-groups/{id}/complete` para cargar datos actuales
3. Usuario modifica lo que necesita
4. Envía PUT `/api/v1/price-groups/{id}/complete` con los cambios
5. Si recibe error 422, muestra los errores de validación
6. Si recibe 200, actualización exitosa
7. Para ver el grupo completo actualizado, llamar de nuevo a GET `/complete`

### 4. **Manejo de Errores**

```javascript
// Errores posibles:
{
  "success": false,
  "message": "Error en validación",
  "errors": {
    "name": ["El nombre ya existe para otro grupo"],
    "cabins.0.prices.0.num_guests": ["Excede la capacidad de la cabaña"],
    "date_ranges.0.end_date": ["El rango se solapa con otro"]
  }
}
```

---

## 📝 Tabla Resumen de Transacciones

| Operación | Endpoint | Método | Transacción | Cascada |
|-----------|----------|--------|-------------|---------|
| Crear grupo completo | `/price-groups/complete` | POST | ✅ Sí | No |
| Editar grupo completo | `/price-groups/{id}/complete` | PUT | ✅ Sí | No |
| Ver grupo completo | `/price-groups/{id}/complete` | GET | ❌ No | N/A |
| Eliminar cabaña | (cascada) | - | - | ✅ Elimina precios |
| Eliminar grupo | `/price-groups/{id}` | DELETE | ❌ No | ✅ Elimina precios y rangos |

---

## 🚀 Endpoints Relacionados Útiles

```
POST   /api/v1/price-groups/complete           # Crear grupo completo
GET    /api/v1/price-groups/{id}/complete      # Ver grupo completo
PUT    /api/v1/price-groups/{id}/complete      # Editar grupo completo

GET    /api/v1/price-groups                    # Listar todos los grupos
GET    /api/v1/price-groups/{id}               # Ver grupo (simple)
POST   /api/v1/price-groups                    # Crear grupo (simple)
PUT    /api/v1/price-groups/{id}               # Editar grupo (simple)
DELETE /api/v1/price-groups/{id}               # Eliminar grupo

GET    /api/v1/price-ranges                    # Listar rangos
POST   /api/v1/price-ranges                    # Crear rango
PUT    /api/v1/price-ranges/{id}               # Editar rango
DELETE /api/v1/price-ranges/{id}               # Eliminar rango
GET    /api/v1/price-ranges/applicable-rates   # Obtener tarifas aplicables
```

---

✅ **Este documento responde todas tus preguntas con ejemplos reales del código actual.**
