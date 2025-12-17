# 🎯 Explicación del Módulo de Tarifas: Estructura Actual y Requisitos

## 📌 Resumen Ejecutivo

Actualmente tienes **3 entidades separadas** en tu sistema de tarifas:

1. **PriceGroup** (Grupos de Precio/Temporadas)
2. **PriceRange** (Rangos de Fechas)
3. **CabinPriceByGuests** (Precios por Cabaña y Cantidad de Huéspedes)

**El problema:** Estas entidades están desconectadas. Necesitas vincularlas adecuadamente para lograr el flujo que describes.

---

## 🔍 Estructura Actual del Sistema

### 1️⃣ **PriceGroup** (Grupos de Precio)

```typescript
interface PriceGroup {
  id: number;
  name: string;                  // "Temporada Alta"
  price_per_night: number;       // ❌ Este campo está OBSOLETO
  is_default: boolean;
  price_ranges?: PriceRange[];   // Relación con rangos de fecha
}
```

**Función actual:**
- Define una categoría de precio (ej: "Temporada Alta", "Año Nuevo")
- Contiene un `price_per_night` que **YA NO DEBERÍA USARSE** (está obsoleto)
- Se vincula con rangos de fechas mediante `PriceRange`

**Problema:**
- El `price_per_night` del grupo NO considera la cabaña ni la cantidad de huéspedes
- Es un precio genérico que no sirve para tu lógica actual

---

### 2️⃣ **PriceRange** (Rangos de Fechas)

```typescript
interface PriceRange {
  id: number;
  price_group_id: number;        // A qué grupo pertenece
  start_date: string;            // "2025-12-15"
  end_date: string;              // "2026-02-28"
  price_group?: PriceGroup;      // Relación con el grupo
}
```

**Función actual:**
- Define CUÁNDO se aplica un grupo de precio
- Ejemplo: "Temporada Alta" se aplica del 15/12/2025 al 28/02/2026

**Problema:**
- Solo define fechas, NO define precios específicos por cabaña

---

### 3️⃣ **CabinPriceByGuests** (Precios por Cabaña y Huéspedes)

```typescript
interface CabinPriceByGuests {
  id: number;
  cabin_id: number;              // Qué cabaña
  price_group_id: number;        // Para qué temporada/grupo
  num_guests: number;            // Para cuántos huéspedes
  price_per_night: number;       // El precio real
  cabin?: Cabin;
  price_group?: PriceGroup;
}
```

**Función actual:**
- Define el precio REAL de una cabaña específica
- Para una cantidad específica de huéspedes
- En un grupo de precio específico (temporada)

**✅ Esta es la entidad CORRECTA** que contiene los precios finales.

---

## 🎯 Flujo Deseado vs Flujo Actual

### ❌ Flujo Actual (Desconectado)

```
1. Creo PriceGroup "Temporada Alta"
   - price_per_night: $20,000 (genérico, no se usa)

2. Creo PriceRange para "Temporada Alta"
   - start_date: 2025-12-15
   - end_date: 2026-02-28

3. Creo CabinPriceByGuests (separado del flujo anterior)
   - cabin_id: 1
   - price_group_id: 1 (Temporada Alta)
   - num_guests: 2
   - price_per_night: $15,000

4. Creo otro CabinPriceByGuests
   - cabin_id: 1
   - price_group_id: 1
   - num_guests: 3
   - price_per_night: $18,000
```

**Problema:** No hay un flujo unificado. Se crean entidades por separado sin guiar al usuario.

---

### ✅ Flujo Deseado (Lo que necesitas)

```
1. Crear Grupo "Temporada Alta"
   ├─ Asignar cabañas que participan
   │  ├─ Cabaña 1: "Deluxe Cabin"
   │  └─ Cabaña 2: "Standard Cabin"
   │
   ├─ Para cada cabaña, definir precios por huéspedes
   │  ├─ Cabaña 1 (capacidad: 4)
   │  │  ├─ 2 personas: $15,000
   │  │  ├─ 3 personas: $18,000
   │  │  └─ 4 personas: $20,000
   │  │
   │  └─ Cabaña 2 (capacidad: 6)
   │     ├─ 2 personas: $12,000
   │     ├─ 3 personas: $14,000
   │     ├─ 4 personas: $16,000
   │     ├─ 5 personas: $18,000
   │     └─ 6 personas: $20,000
   │
   └─ FINALMENTE, asignar rangos de fechas
      ├─ Rango 1: 15/12/2025 - 28/02/2026
      └─ Rango 2: 01/07/2025 - 31/07/2025
```

**Este flujo garantiza:**
- Que cada grupo tiene cabañas asignadas
- Que cada cabaña tiene precios por cantidad de huéspedes
- Que los rangos de fecha se asignan AL FINAL, cuando todo está configurado

---

## 🛠️ Cómo Lograr Esto Actualmente

### Opción 1: Usar el Sistema Actual (Múltiples Requests)

**Frontend debe hacer esto:**

```typescript
// PASO 1: Crear el grupo de precio
const createPriceGroup = async () => {
  const group = await priceGroupsService.create({
    name: "Temporada Alta",
    price_per_night: 0,  // ⚠️ Obligatorio pero NO SE USA
    is_default: false
  });
  return group;
};

// PASO 2: Asignar precios por cabaña y huéspedes
const assignCabinPrices = async (groupId: number, cabinId: number) => {
  // Para cada cantidad de huéspedes...
  await cabinPricesService.create({
    cabin_id: cabinId,
    price_group_id: groupId,
    num_guests: 2,
    price_per_night: 15000
  });
  
  await cabinPricesService.create({
    cabin_id: cabinId,
    price_group_id: groupId,
    num_guests: 3,
    price_per_night: 18000
  });
  
  await cabinPricesService.create({
    cabin_id: cabinId,
    price_group_id: groupId,
    num_guests: 4,
    price_per_night: 20000
  });
};

// PASO 3: Asignar rangos de fecha
const assignDateRanges = async (groupId: number) => {
  await priceRangesService.create({
    price_group_id: groupId,
    start_date: "2025-12-15",
    end_date: "2026-02-28"
  });
};

// FLUJO COMPLETO
const createCompleteRateGroup = async () => {
  // 1. Crear grupo
  const group = await createPriceGroup();
  
  // 2. Asignar precios para cabaña 1
  await assignCabinPrices(group.data.id, 1);
  
  // 3. Asignar precios para cabaña 2
  await assignCabinPrices(group.data.id, 2);
  
  // 4. Asignar rangos de fecha
  await assignDateRanges(group.data.id);
};
```

**✅ Esto funciona pero requiere múltiples requests**

---

### Opción 2: Crear un Endpoint Batch en el Backend (Recomendado)

**Necesitas crear un nuevo endpoint en el backend:**

```
POST /api/v1/price-groups/complete
```

**Request Body:**

```typescript
interface CreateCompletePriceGroupRequest {
  name: string;                    // "Temporada Alta"
  is_default: boolean;             // false
  
  cabins: Array<{
    cabin_id: number;              // ID de la cabaña
    prices: Array<{
      num_guests: number;          // 2, 3, 4...
      price_per_night: number;     // $15,000, $18,000...
    }>;
  }>;
  
  date_ranges: Array<{
    start_date: string;            // "2025-12-15"
    end_date: string;              // "2026-02-28"
  }>;
}
```

**Ejemplo de uso:**

```json
{
  "name": "Temporada Alta",
  "is_default": false,
  "cabins": [
    {
      "cabin_id": 1,
      "prices": [
        { "num_guests": 2, "price_per_night": 15000 },
        { "num_guests": 3, "price_per_night": 18000 },
        { "num_guests": 4, "price_per_night": 20000 }
      ]
    },
    {
      "cabin_id": 2,
      "prices": [
        { "num_guests": 2, "price_per_night": 12000 },
        { "num_guests": 3, "price_per_night": 14000 },
        { "num_guests": 4, "price_per_night": 16000 },
        { "num_guests": 5, "price_per_night": 18000 },
        { "num_guests": 6, "price_per_night": 20000 }
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

**Lógica en el Backend (PHP/Laravel):**

```php
public function storeComplete(CreateCompletePriceGroupRequest $request)
{
    DB::transaction(function () use ($request) {
        // 1. Crear el PriceGroup
        $priceGroup = PriceGroup::create([
            'name' => $request->name,
            'price_per_night' => 0, // Obsoleto pero obligatorio
            'is_default' => $request->is_default,
            'tenant_id' => auth()->user()->tenant_id
        ]);

        // 2. Crear los precios por cabaña y huéspedes
        foreach ($request->cabins as $cabinData) {
            foreach ($cabinData['prices'] as $priceData) {
                CabinPriceByGuests::create([
                    'cabin_id' => $cabinData['cabin_id'],
                    'price_group_id' => $priceGroup->id,
                    'num_guests' => $priceData['num_guests'],
                    'price_per_night' => $priceData['price_per_night'],
                    'tenant_id' => auth()->user()->tenant_id
                ]);
            }
        }

        // 3. Crear los rangos de fecha
        foreach ($request->date_ranges as $rangeData) {
            PriceRange::create([
                'price_group_id' => $priceGroup->id,
                'start_date' => $rangeData['start_date'],
                'end_date' => $rangeData['end_date'],
                'tenant_id' => auth()->user()->tenant_id
            ]);
        }

        return $priceGroup->load(['priceRanges', 'cabinPrices']);
    });
}
```

---

## 📊 Endpoints del Backend que Necesitas

### ✅ Endpoints que YA TIENES (funcionan)

```bash
# Price Groups
GET    /api/v1/price-groups
POST   /api/v1/price-groups
PUT    /api/v1/price-groups/{id}
DELETE /api/v1/price-groups/{id}

# Price Ranges
GET    /api/v1/price-ranges
POST   /api/v1/price-ranges
PUT    /api/v1/price-ranges/{id}
DELETE /api/v1/price-ranges/{id}

# Cabin Prices By Guests
GET    /api/v1/cabin-prices-by-guests
GET    /api/v1/cabin-prices-by-guests/cabin/{cabinId}
POST   /api/v1/cabin-prices-by-guests
PUT    /api/v1/cabin-prices-by-guests/{id}
DELETE /api/v1/cabin-prices-by-guests/{id}
```

### ❌ Endpoints que NECESITAS CREAR

```bash
# Crear grupo completo (grupo + cabañas + precios + rangos)
POST /api/v1/price-groups/complete

# Actualizar grupo completo
PUT /api/v1/price-groups/{id}/complete

# Obtener grupo con todas sus relaciones
GET /api/v1/price-groups/{id}/complete

# Batch: Crear/actualizar múltiples precios de cabaña a la vez
POST /api/v1/cabin-prices-by-guests/batch
PUT /api/v1/cabin-prices-by-guests/batch

# Obtener todas las cabañas de un grupo de precio
GET /api/v1/price-groups/{id}/cabins

# Obtener todos los precios de un grupo de precio
GET /api/v1/price-groups/{id}/cabin-prices
```

---

## 🔄 Cómo Obtener los Precios en una Reserva

### Flujo de Cálculo de Precio

```typescript
// PASO 1: Usuario selecciona fechas
const checkIn = "2025-12-20";
const checkOut = "2025-12-25";
const cabinId = 1;
const numGuests = 3;

// PASO 2: Backend busca qué PriceRange aplica para esas fechas
const priceRanges = await PriceRange.query()
  .where('start_date', '<=', checkIn)
  .where('end_date', '>=', checkOut)
  .get();

// PASO 3: Obtener el price_group_id del rango
const priceGroupId = priceRanges[0].price_group_id;

// PASO 4: Buscar el precio específico
const cabinPrice = await CabinPriceByGuests.query()
  .where('cabin_id', cabinId)
  .where('price_group_id', priceGroupId)
  .where('num_guests', numGuests)
  .first();

// PASO 5: Calcular precio total
const nights = calculateNights(checkIn, checkOut); // 5 noches
const totalPrice = cabinPrice.price_per_night * nights; // $18,000 × 5 = $90,000
```

**Este endpoint debería existir:**

```
POST /api/v1/reservations/calculate-price
```

**Request:**

```json
{
  "cabin_id": 1,
  "check_in_date": "2025-12-20",
  "check_out_date": "2025-12-25",
  "num_guests": 3
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "cabin_id": 1,
    "check_in_date": "2025-12-20",
    "check_out_date": "2025-12-25",
    "num_guests": 3,
    "nights": 5,
    "price_per_night": 18000,
    "total_price": 90000,
    "deposit_amount": 27000,
    "balance_amount": 63000,
    "pricing_breakdown": [
      {
        "date": "2025-12-20",
        "price": 18000,
        "price_group": "Temporada Alta"
      },
      {
        "date": "2025-12-21",
        "price": 18000,
        "price_group": "Temporada Alta"
      },
      // ... resto de las noches
    ]
  }
}
```

---

## 🎨 UI/UX Sugerido para el Frontend

### Wizard/Stepper para Crear Grupo de Precio

```
┌─────────────────────────────────────────────────────┐
│ Crear Grupo de Precio - Paso 1 de 4                │
├─────────────────────────────────────────────────────┤
│                                                     │
│ ● Datos del Grupo  ○ Cabañas  ○ Precios  ○ Fechas  │
│                                                     │
│ Nombre: [Temporada Alta_______________]            │
│                                                     │
│ □ Es el grupo por defecto                          │
│                                                     │
│              [Cancelar]  [Siguiente →]             │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Crear Grupo de Precio - Paso 2 de 4                │
├─────────────────────────────────────────────────────┤
│                                                     │
│ ○ Datos del Grupo  ● Cabañas  ○ Precios  ○ Fechas  │
│                                                     │
│ Selecciona las cabañas para esta temporada:        │
│                                                     │
│ ☑ Cabaña Deluxe (Capacidad: 4)                     │
│ ☑ Cabaña Standard (Capacidad: 6)                   │
│ ☐ Cabaña Economy (Capacidad: 2)                    │
│                                                     │
│              [← Anterior]  [Siguiente →]           │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Crear Grupo de Precio - Paso 3 de 4                │
├─────────────────────────────────────────────────────┤
│                                                     │
│ ○ Datos  ○ Cabañas  ● Precios por Huéspedes  ○ Fechas│
│                                                     │
│ ► Cabaña Deluxe (Capacidad: 4)                     │
│   2 personas: [$15,000_____]                       │
│   3 personas: [$18,000_____]                       │
│   4 personas: [$20,000_____]                       │
│                                                     │
│ ► Cabaña Standard (Capacidad: 6)                   │
│   2 personas: [$12,000_____]                       │
│   3 personas: [$14,000_____]                       │
│   4 personas: [$16,000_____]                       │
│   5 personas: [$18,000_____]                       │
│   6 personas: [$20,000_____]                       │
│                                                     │
│              [← Anterior]  [Siguiente →]           │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ Crear Grupo de Precio - Paso 4 de 4                │
├─────────────────────────────────────────────────────┤
│                                                     │
│ ○ Datos  ○ Cabañas  ○ Precios  ● Rangos de Fechas  │
│                                                     │
│ Define cuándo se aplicará esta temporada:          │
│                                                     │
│ Rango 1:                                            │
│   Desde: [15/12/2025]  Hasta: [28/02/2026]         │
│   [Eliminar]                                        │
│                                                     │
│ Rango 2:                                            │
│   Desde: [01/07/2025]  Hasta: [31/07/2025]         │
│   [Eliminar]                                        │
│                                                     │
│ [+ Agregar Rango]                                   │
│                                                     │
│              [← Anterior]  [Crear Grupo]           │
└─────────────────────────────────────────────────────┘
```

---

## 📋 Checklist de Implementación Backend

### ✅ Endpoints que YA EXISTEN (no tocar)

- [x] `GET /price-groups` - Listar grupos
- [x] `POST /price-groups` - Crear grupo básico
- [x] `PUT /price-groups/{id}` - Actualizar grupo
- [x] `DELETE /price-groups/{id}` - Eliminar grupo
- [x] `GET /price-ranges` - Listar rangos
- [x] `POST /price-ranges` - Crear rango
- [x] `PUT /price-ranges/{id}` - Actualizar rango
- [x] `DELETE /price-ranges/{id}` - Eliminar rango
- [x] `GET /cabin-prices-by-guests` - Listar precios
- [x] `GET /cabin-prices-by-guests/cabin/{id}` - Precios de una cabaña
- [x] `POST /cabin-prices-by-guests` - Crear precio
- [x] `PUT /cabin-prices-by-guests/{id}` - Actualizar precio
- [x] `DELETE /cabin-prices-by-guests/{id}` - Eliminar precio

### ❌ Endpoints que DEBES CREAR

- [ ] `POST /price-groups/complete` - Crear grupo completo (grupo + cabañas + precios + rangos)
- [ ] `PUT /price-groups/{id}/complete` - Actualizar grupo completo
- [ ] `GET /price-groups/{id}/complete` - Obtener grupo con todas sus relaciones (incluir cabin_prices)
- [ ] `POST /cabin-prices-by-guests/batch` - Crear múltiples precios a la vez
- [ ] `PUT /cabin-prices-by-guests/batch` - Actualizar múltiples precios
- [ ] `GET /price-groups/{id}/cabins` - Cabañas asignadas a un grupo
- [ ] `GET /price-groups/{id}/cabin-prices` - Todos los precios de un grupo
- [ ] `POST /reservations/calculate-price` - Calcular precio basado en fechas, cabaña y huéspedes

### ⚠️ Modificaciones a Modelos Existentes

- [ ] Agregar relación `hasMany` de `PriceGroup` a `CabinPriceByGuests`
- [ ] Agregar scope en `CabinPriceByGuests` para filtrar por grupo y cabaña
- [ ] Marcar `price_per_night` de `PriceGroup` como DEPRECATED (opcional en validación)

### 🔍 Validaciones Adicionales

- [ ] Validar que `num_guests` no exceda la capacidad de la cabaña
- [ ] Validar que no existan duplicados (cabin_id + price_group_id + num_guests)
- [ ] Validar que los rangos de fecha no se solapen para el mismo grupo
- [ ] Validar que el grupo por defecto tenga al menos una cabaña con precios

---

## 🎯 Resumen y Respuesta a tu Pregunta

### ¿Cómo logras esto actualmente?

**Opción A (Ya funciona):** Múltiples requests

```typescript
// 1. Crear grupo
const group = await priceGroupsService.create({ name: "Temporada Alta", ... });

// 2. Por cada cabaña, crear precios
for (let cabin of selectedCabins) {
  for (let guestCount = 2; guestCount <= cabin.capacity; guestCount++) {
    await cabinPricesService.create({
      cabin_id: cabin.id,
      price_group_id: group.data.id,
      num_guests: guestCount,
      price_per_night: prices[guestCount]
    });
  }
}

// 3. Crear rangos de fecha
for (let range of dateRanges) {
  await priceRangesService.create({
    price_group_id: group.data.id,
    start_date: range.start,
    end_date: range.end
  });
}
```

**Opción B (Recomendado):** Crear endpoint batch en backend

```typescript
// Un solo request
await priceGroupsService.createComplete({
  name: "Temporada Alta",
  cabins: [
    {
      cabin_id: 1,
      prices: [
        { num_guests: 2, price_per_night: 15000 },
        { num_guests: 3, price_per_night: 18000 }
      ]
    }
  ],
  date_ranges: [
    { start_date: "2025-12-15", end_date: "2026-02-28" }
  ]
});
```

### ¿Qué necesitas crear en el backend?

1. **Endpoint `POST /price-groups/complete`** - Crear todo en una transacción
2. **Endpoint `GET /price-groups/{id}/complete`** - Obtener grupo con precios por cabaña
3. **Endpoint `POST /reservations/calculate-price`** - Calcular precio considerando num_guests
4. **Relación en el modelo:** `PriceGroup` → `hasMany('cabin_prices')`

### ¿El sistema actual soporta tu flujo?

**SÍ**, pero de manera fragmentada. Con los endpoints batch será mucho más eficiente.

---

## 📝 Próximos Pasos Recomendados

1. **Crear el endpoint `POST /price-groups/complete`** en el backend
2. **Modificar el frontend** para usar un wizard/stepper
3. **Actualizar la página de tarifas** para mostrar las cabañas asignadas por grupo
4. **Implementar el cálculo de precios** considerando `num_guests` en las reservas
5. **Migrar datos existentes** si tienes grupos con `price_per_night` obsoleto

---

**¿Necesitas ayuda implementando alguno de estos endpoints en el backend?** Puedo ayudarte con el código PHP/Laravel específico.
