# Sistema de Precios Multinivel por Cabaña y Cantidad de Huéspedes

## Descripción General

Este nuevo módulo permite al administrador configurar precios individuales para cada cabaña basados en la cantidad de huéspedes. Esto proporciona flexibilidad total para aplicar diferentes precios según la ocupación.

**Ejemplo:**

-   Cabaña 1 (Temporada Alta):
    -   2 personas: $120,000
    -   3 personas: $140,000
    -   4 personas: $160,000

## Estructura de la Solución

### Componentes Principales

#### 1. **Migración**: `cabin_price_by_guests`

-   **Tabla**: `cabin_price_by_guests`
-   **Campos principales**:
    -   `id`: Identificador único
    -   `tenant_id`: Identificador del inquilino (multi-tenancy)
    -   `cabin_id`: Referencia a la cabaña
    -   `price_group_id`: Referencia al grupo de precio (permite temporadas)
    -   `num_guests`: Cantidad de huéspedes (2, 3, 4, etc.)
    -   `price_per_night`: Precio por noche para esa cantidad de huéspedes
    -   `timestamps` y `soft_deletes`

**Índices**:

-   `unique_cabin_guest_price`: Garantiza un único precio por (tenant, cabin, price_group, num_guests)

#### 2. **Modelo**: `CabinPriceByGuests`

```php
class CabinPriceByGuests extends Model
{
    public function cabin(): BelongsTo
    public function priceGroup(): BelongsTo
}
```

#### 3. **Servicio**: `CabinPriceByGuestsService`

Métodos principales:

-   `getCabinPricesByGuests(array $params)` - Listar con filtros
-   `getPricesByCabin(int $cabinId, array $params)` - Precios de una cabaña específica
-   `getPriceForCabinAndGuests(int $cabinId, int $numGuests, int $priceGroupId)` - Obtener precio específico
-   `createCabinPriceByGuests(array $data)` - Crear nuevo precio
-   `updateCabinPriceByGuests(int $id, array $data)` - Actualizar precio
-   `deleteCabinPriceByGuests(int $id)` - Eliminar precio
-   `deletePricesByCabinAndGroup(int $cabinId, int $priceGroupId)` - Eliminar precios de cabaña para un grupo

Filtros soportados:

-   `cabin_id`
-   `price_group_id`
-   `num_guests`

#### 4. **Controlador**: `CabinPriceByGuestsController`

Endpoints disponibles:

-   `GET /api/v1/cabin-prices-by-guests` - Listar todos con filtros
-   `POST /api/v1/cabin-prices-by-guests` - Crear nuevo precio
-   `GET /api/v1/cabin-prices-by-guests/{id}` - Obtener uno
-   `PUT /api/v1/cabin-prices-by-guests/{id}` - Actualizar
-   `DELETE /api/v1/cabin-prices-by-guests/{id}` - Eliminar
-   `GET /api/v1/cabin-prices-by-guests/cabin/{cabinId}` - Listar precios de una cabaña

#### 5. **Request Validation**: `CabinPriceByGuestsRequest`

Validaciones:

-   `cabin_id`: Requerido, debe existir en tabla `cabins`
-   `price_group_id`: Requerido, debe existir en tabla `price_groups`
-   `num_guests`: Requerido, entero mínimo 1, máximo 255
-   `price_per_night`: Requerido, numérico, mínimo 0, máximo 999999.99

#### 6. **Resource**: `CabinPriceByGuestsResource`

Retorna los datos del precio con relaciones cargadas:

-   `id`, `cabin_id`, `price_group_id`, `num_guests`, `price_per_night`
-   Relaciones: `cabin`, `price_group`

#### 7. **Servicio Actualizado**: `PriceCalculatorService`

Métodos mejorados con soporte para precios por cabaña y cantidad de huéspedes:

```php
// Ahora acepta parámetros opcionales para cabaña y cantidad de huéspedes
public function calculatePrice(
    Carbon $checkIn,
    Carbon $checkOut,
    ?int $cabinId = null,
    ?int $numGuests = null
): array

// Obtener precio para una fecha considerando cabaña y huéspedes
public function getPriceForDate(
    Carbon $date,
    ?int $cabinId = null,
    ?int $numGuests = null
): float

// Generar cotización con soporte para precios personalizados
public function generateQuote(
    int $cabinId,
    string $checkIn,
    string $checkOut,
    ?int $numGuests = null
): array
```

### Lógica de Precedencia de Precios

El sistema busca precios en este orden:

1. **Precio específico por cabaña, cantidad de huéspedes y grupo de precio**

    - Si existe `CabinPriceByGuests` para (cabin_id, num_guests, price_group_id), usar ese

2. **Precio del grupo de precio para la fecha**

    - Si existe `PriceRange` que cubra la fecha, usar el `price_per_night` del `PriceGroup`

3. **Precio del grupo por defecto**

    - Si existe un `PriceGroup` con `is_default = true`, usar ese

4. **Sin precio configurado**
    - Retornar 0

## Endpoints API

### 1. Listar todos los precios por cabaña y huéspedes

```bash
GET /api/v1/cabin-prices-by-guests?page=1&per_page=50&sort_by=num_guests&sort_order=asc
```

**Filtros disponibles**:

-   `cabin_id`: Filtrar por cabaña
-   `price_group_id`: Filtrar por grupo de precio
-   `num_guests`: Filtrar por cantidad de huéspedes

**Ejemplo con filtros**:

```bash
GET /api/v1/cabin-prices-by-guests?cabin_id=1&price_group_id=2&num_guests=4
```

### 2. Crear nuevo precio

```bash
POST /api/v1/cabin-prices-by-guests
Content-Type: application/json

{
  "cabin_id": 1,
  "price_group_id": 2,
  "num_guests": 4,
  "price_per_night": 160000
}
```

**Respuesta (201)**:

```json
{
  "data": {
    "id": 1,
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": 4,
    "price_per_night": 160000,
    "cabin": { ... },
    "price_group": { ... },
    "created_at": "2025-12-16T10:30:00Z",
    "updated_at": "2025-12-16T10:30:00Z"
  },
  "message": "Precio de cabaña por cantidad de huéspedes creado exitosamente"
}
```

### 3. Obtener precio específico

```bash
GET /api/v1/cabin-prices-by-guests/1
```

### 4. Actualizar precio

```bash
PUT /api/v1/cabin-prices-by-guests/1
Content-Type: application/json

{
  "price_per_night": 170000
}
```

### 5. Eliminar precio

```bash
DELETE /api/v1/cabin-prices-by-guests/1
```

### 6. Listar precios de una cabaña específica

```bash
GET /api/v1/cabin-prices-by-guests/cabin/1?page=1&per_page=50&sort_by=num_guests&sort_order=asc
```

## Relaciones en Modelos

### Cabin

```php
public function pricesByGuests(): HasMany
{
    return $this->hasMany(CabinPriceByGuests::class);
}
```

### PriceGroup

```php
public function cabinPricesByGuests(): HasMany
{
    return $this->hasMany(CabinPriceByGuests::class);
}
```

El hook de eliminación en cascada asegura que los precios se eliminen cuando se elimina un grupo de precio.

## Factory para Pruebas

```php
// Crear un precio aleatorio
$price = CabinPriceByGuests::factory()->create();

// Crear múltiples precios
$prices = CabinPriceByGuests::factory()->count(10)->create();

// Crear con datos específicos
$price = CabinPriceByGuests::factory()->create([
    'cabin_id' => 1,
    'price_group_id' => 2,
    'num_guests' => 4,
]);
```

## Ejemplo de Uso Completo

### Scenario: Configurar precios para Cabaña 1 en temporada alta

**Suposiciones**:

-   Cabaña ID: 1 (Cabaña Deluxe)
-   Grupo de Precio ID: 2 (Temporada Alta)

**Paso 1: Crear precio para 2 personas**

```bash
POST /api/v1/cabin-prices-by-guests
{
  "cabin_id": 1,
  "price_group_id": 2,
  "num_guests": 2,
  "price_per_night": 120000
}
```

**Paso 2: Crear precio para 3 personas**

```bash
POST /api/v1/cabin-prices-by-guests
{
  "cabin_id": 1,
  "price_group_id": 2,
  "num_guests": 3,
  "price_per_night": 140000
}
```

**Paso 3: Crear precio para 4 personas**

```bash
POST /api/v1/cabin-prices-by-guests
{
  "cabin_id": 1,
  "price_group_id": 2,
  "num_guests": 4,
  "price_per_night": 160000
}
```

**Paso 4: Consultar precios de la cabaña**

```bash
GET /api/v1/cabin-prices-by-guests/cabin/1
```

### Cálculo automático de reservas

Cuando se genera una cotización o se calcula el precio de una reserva:

```php
// Incluir num_guests en el cálculo
$priceDetails = $priceCalculatorService->calculatePrice(
    checkIn: Carbon::parse('2025-12-20'),
    checkOut: Carbon::parse('2025-12-23'),
    cabinId: 1,
    numGuests: 4  // Sistema buscará precio para 4 personas
);

// Resultado:
// Si hay CabinPriceByGuests(cabin=1, price_group=2, num_guests=4, price=160000)
// Y el rango de precio cubre 2025-12-20 a 2025-12-23 con price_group_id=2
// Entonces: total = 160000 * 3 noches = 480000
```

## Validación y Restricciones

-   Cada combinación de (tenant, cabin, price_group, num_guests) es única
-   num_guests debe ser un entero positivo (mínimo 1)
-   price_per_night debe ser numérico no negativo
-   Las referencias a cabin_id y price_group_id deben existir

## Migración y Rollback

```bash
# Ejecutar migración
php artisan migrate

# Revertir migración
php artisan migrate:rollback --step=1
```

## Notas de Diseño

✅ **Ventajas**:

-   Máxima flexibilidad en configuración de precios
-   Soporta escalabilidad a N cantidad de personas
-   Mantiene compatibilidad con sistema de temporadas (price_groups)
-   Separación clara de responsabilidades
-   Índice único previene duplicados
-   Soft deletes para historial

✅ **Características**:

-   Multi-tenant: Aislamiento de datos por tenant
-   Paginación y filtrado en todos los endpoints
-   Validación robusta en requests
-   Resources para formateo consistente
-   Factory para testing automático
-   Relaciones bien definidas
-   Cascada de eliminación en relaciones

✅ **Mejoras futuras**:

-   Descuentos por cantidad de noches
-   Recargos por temporada especial
-   Variaciones por día de la semana
-   Reportes de precios configurados vs. utilizados
