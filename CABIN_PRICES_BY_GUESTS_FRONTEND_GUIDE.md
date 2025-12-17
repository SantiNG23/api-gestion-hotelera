# Guía Completa de Precios por Cabaña y Cantidad de Huéspedes - Para Frontend

Este documento describe en detalle cómo el frontend debe interactuar con el sistema de precios por cabaña y cantidad de huéspedes.

## 📋 Tabla de Contenidos

1. [Conceptos Básicos](#conceptos-básicos)
2. [Estructura de Datos](#estructura-de-datos)
3. [Endpoints y Requests](#endpoints-y-requests)
4. [Respuestas Esperadas](#respuestas-esperadas)
5. [Validaciones](#validaciones)
6. [Casos de Uso](#casos-de-uso)
7. [Errores Comunes](#errores-comunes)
8. [Integración con Otras Funcionalidades](#integración-con-otras-funcionalidades)

---

## Conceptos Básicos

### ¿Qué es un "Precio por Cabaña y Cantidad de Huéspedes"?

Es una configuración individual que establece el precio por noche de una cabaña específica para una cantidad específica de huéspedes en un período de tiempo específico (grupo de precio/temporada).

**Ejemplo:**

```
Cabaña: "Deluxe Cabin"
Temporada: "Verano" (junio-agosto)
2 personas: $120,000 por noche
3 personas: $140,000 por noche
4 personas: $160,000 por noche
```

### Estructura Jerárquica

```
Tenant (Propiedad)
└── Cabaña
    └── Precios por Cantidad de Huéspedes
        ├── Grupo de Precio (Temporada)
        │   ├── 2 personas → $120,000
        │   ├── 3 personas → $140,000
        │   └── 4 personas → $160,000
        └── Otro Grupo de Precio (Otra Temporada)
            ├── 2 personas → $100,000
            ├── 3 personas → $115,000
            └── 4 personas → $130,000
```

---

## Estructura de Datos

### Modelo: CabinPriceByGuests

```typescript
interface CabinPriceByGuests {
    id: number; // ID único del registro
    cabin_id: number; // ID de la cabaña
    price_group_id: number; // ID del grupo de precio (temporada)
    num_guests: number; // Cantidad de huéspedes (2-255)
    price_per_night: number; // Precio por noche (decimal)
    cabin?: Cabin; // Objeto cabaña (cargado con ?include=cabin)
    price_group?: PriceGroup; // Objeto grupo precio (cargado con ?include=price_group)
    created_at: string; // Fecha creación (ISO 8601)
    updated_at: string; // Fecha actualización (ISO 8601)
}
```

### Validaciones de Entrada

```typescript
interface CreateCabinPriceByGuestsRequest {
    cabin_id: number; // Requerido, debe existir
    price_group_id: number; // Requerido, debe existir
    num_guests: number; // Requerido, 1 <= x <= 255
    price_per_night: number; // Requerido, >= 0, <= 999999.99
}

interface UpdateCabinPriceByGuestsRequest {
    cabin_id?: number; // Opcional
    price_group_id?: number; // Opcional
    num_guests?: number; // Opcional
    price_per_night?: number; // Opcional
}
```

---

## Endpoints y Requests

### 1. Listar Todos los Precios

**URL:**

```
GET /api/v1/cabin-prices-by-guests
```

**Query Parameters:**

| Parámetro        | Tipo    | Requerido | Descripción                        |
| ---------------- | ------- | --------- | ---------------------------------- |
| `page`           | integer | No        | Número de página (default: 1)      |
| `per_page`       | integer | No        | Registros por página (default: 50) |
| `sort_by`        | string  | No        | Campo para ordenar (default: id)   |
| `sort_order`     | string  | No        | 'asc' o 'desc' (default: asc)      |
| `cabin_id`       | integer | No        | Filtrar por cabaña                 |
| `price_group_id` | integer | No        | Filtrar por grupo de precio        |
| `num_guests`     | integer | No        | Filtrar por cantidad huéspedes     |

**Ejemplos:**

```bash
# Listar todos con paginación
GET /api/v1/cabin-prices-by-guests?page=1&per_page=50

# Filtrar por cabaña y ordenar por cantidad de huéspedes
GET /api/v1/cabin-prices-by-guests?cabin_id=1&sort_by=num_guests&sort_order=asc

# Filtrar por grupo de precio (temporada)
GET /api/v1/cabin-prices-by-guests?price_group_id=2

# Múltiples filtros
GET /api/v1/cabin-prices-by-guests?cabin_id=1&price_group_id=2&num_guests=4
```

**Respuesta (200):**

```json
{
    "data": [
        {
            "id": 1,
            "cabin_id": 1,
            "price_group_id": 2,
            "num_guests": 2,
            "price_per_night": 120000,
            "cabin": null,
            "price_group": null,
            "created_at": "2025-12-16T10:30:00Z",
            "updated_at": "2025-12-16T10:30:00Z"
        },
        {
            "id": 2,
            "cabin_id": 1,
            "price_group_id": 2,
            "num_guests": 3,
            "price_per_night": 140000,
            "cabin": null,
            "price_group": null,
            "created_at": "2025-12-16T10:31:00Z",
            "updated_at": "2025-12-16T10:31:00Z"
        }
    ],
    "links": {
        "first": "http://api.local/api/v1/cabin-prices-by-guests?page=1",
        "last": "http://api.local/api/v1/cabin-prices-by-guests?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 50,
        "to": 2,
        "total": 2
    }
}
```

---

### 2. Listar Precios de una Cabaña Específica

**URL:**

```
GET /api/v1/cabin-prices-by-guests/cabin/{cabinId}
```

**Query Parameters:** (Iguales a listar todos)

**Ejemplo:**

```bash
GET /api/v1/cabin-prices-by-guests/cabin/1?sort_by=num_guests&sort_order=asc
```

**Respuesta (200):**

```json
{
  "data": [
    {
      "id": 1,
      "cabin_id": 1,
      "price_group_id": 2,
      "num_guests": 2,
      "price_per_night": 120000,
      "created_at": "2025-12-16T10:30:00Z",
      "updated_at": "2025-12-16T10:30:00Z"
    },
    {
      "id": 2,
      "cabin_id": 1,
      "price_group_id": 2,
      "num_guests": 3,
      "price_per_night": 140000,
      "created_at": "2025-12-16T10:31:00Z",
      "updated_at": "2025-12-16T10:31:00Z"
    },
    {
      "id": 3,
      "cabin_id": 1,
      "price_group_id": 2,
      "num_guests": 4,
      "price_per_night": 160000,
      "created_at": "2025-12-16T10:32:00Z",
      "updated_at": "2025-12-16T10:32:00Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

### 3. Crear Nuevo Precio

**URL:**

```
POST /api/v1/cabin-prices-by-guests
```

**Content-Type:**

```
application/json
```

**Body (JSON):**

```json
{
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": 4,
    "price_per_night": 160000
}
```

**Ejemplo con cURL:**

```bash
curl -X POST http://api.local/api/v1/cabin-prices-by-guests \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": 4,
    "price_per_night": 160000
  }'
```

**Respuesta (201):**

```json
{
    "data": {
        "id": 4,
        "cabin_id": 1,
        "price_group_id": 2,
        "num_guests": 4,
        "price_per_night": 160000,
        "cabin": {
            "id": 1,
            "tenant_id": 1,
            "name": "Deluxe Cabin",
            "description": "Una lujosa cabaña...",
            "capacity": 6,
            "is_active": true,
            "created_at": "2025-12-16T10:00:00Z",
            "updated_at": "2025-12-16T10:00:00Z"
        },
        "price_group": {
            "id": 2,
            "tenant_id": 1,
            "name": "Temporada Alta",
            "price_per_night": 100000,
            "priority": 1,
            "is_default": false,
            "created_at": "2025-12-16T09:00:00Z",
            "updated_at": "2025-12-16T09:00:00Z"
        },
        "created_at": "2025-12-16T10:35:00Z",
        "updated_at": "2025-12-16T10:35:00Z"
    },
    "message": "Precio de cabaña por cantidad de huéspedes creado exitosamente"
}
```

---

### 4. Obtener un Precio Específico

**URL:**

```
GET /api/v1/cabin-prices-by-guests/{id}
```

**Ejemplo:**

```bash
GET /api/v1/cabin-prices-by-guests/4
```

**Respuesta (200):**

```json
{
  "data": {
    "id": 4,
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": 4,
    "price_per_night": 160000,
    "cabin": { ... },
    "price_group": { ... },
    "created_at": "2025-12-16T10:35:00Z",
    "updated_at": "2025-12-16T10:35:00Z"
  }
}
```

**Respuesta (404):**

```json
{
    "message": "No query results found for model [App\\Models\\CabinPriceByGuests] 4"
}
```

---

### 5. Actualizar un Precio

**URL:**

```
PUT /api/v1/cabin-prices-by-guests/{id}
```

**Body (JSON):**

```json
{
    "price_per_night": 170000
}
```

**Ejemplo completo:**

```bash
curl -X PUT http://api.local/api/v1/cabin-prices-by-guests/4 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "price_per_night": 170000
  }'
```

**Respuesta (200):**

```json
{
  "data": {
    "id": 4,
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": 4,
    "price_per_night": 170000,
    "cabin": { ... },
    "price_group": { ... },
    "created_at": "2025-12-16T10:35:00Z",
    "updated_at": "2025-12-16T10:40:00Z"
  },
  "message": "Precio de cabaña por cantidad de huéspedes actualizado exitosamente"
}
```

---

### 6. Eliminar un Precio

**URL:**

```
DELETE /api/v1/cabin-prices-by-guests/{id}
```

**Ejemplo:**

```bash
curl -X DELETE http://api.local/api/v1/cabin-prices-by-guests/4 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Respuesta (200):**

```json
{
    "data": null,
    "message": "Precio de cabaña por cantidad de huéspedes eliminado exitosamente"
}
```

**Respuesta (404):**

```json
{
    "message": "No query results found for model [App\\Models\\CabinPriceByGuests] 999"
}
```

---

## Respuestas Esperadas

### Estructura General de Respuesta Exitosa

```json
{
  "data": { ... },
  "message": "Descripción del resultado"
}
```

### Códigos de Estado HTTP

| Código | Descripción                               |
| ------ | ----------------------------------------- |
| 200    | OK - Operación exitosa                    |
| 201    | Created - Recurso creado exitosamente     |
| 400    | Bad Request - Datos inválidos             |
| 404    | Not Found - Recurso no encontrado         |
| 422    | Unprocessable Entity - Validación fallida |
| 500    | Server Error - Error del servidor         |

---

## Validaciones

### Validaciones de Campo

#### `cabin_id`

-   **Requerido**: Sí
-   **Tipo**: Entero
-   **Restricciones**: Debe existir en tabla `cabins` del mismo tenant
-   **Mensaje error**: "La cabaña seleccionada no existe"

#### `price_group_id`

-   **Requerido**: Sí
-   **Tipo**: Entero
-   **Restricciones**: Debe existir en tabla `price_groups` del mismo tenant
-   **Mensaje error**: "El grupo de precio seleccionado no existe"

#### `num_guests`

-   **Requerido**: Sí
-   **Tipo**: Entero
-   **Restricciones**: `1 <= num_guests <= 255`
-   **Mensajes error**:
    -   "La cantidad de huéspedes es obligatoria"
    -   "La cantidad de huéspedes debe ser un número entero"
    -   "La cantidad de huéspedes debe ser al menos 1"

#### `price_per_night`

-   **Requerido**: Sí
-   **Tipo**: Número decimal
-   **Restricciones**: `0 <= price_per_night <= 999999.99`
-   **Mensajes error**:
    -   "El precio por noche es obligatorio"
    -   "El precio debe ser un número"
    -   "El precio no puede ser negativo"

### Validación de Unicidad

No puedes crear dos precios con los mismos valores para:

-   `tenant_id` (automático)
-   `cabin_id`
-   `price_group_id`
-   `num_guests`

**Si intentas:**

```json
{
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": 4,
    "price_per_night": 160000
}
```

Y ya existe ese registro, obtendrás:

```json
{
    "message": "The selected value is invalid.",
    "errors": {
        "cabin_id": ["..."]
    }
}
```

---

## Casos de Uso

### Caso 1: Configurar Precios para una Cabaña en Temporada Alta

**Objetivo**: Establecer precios escalonados para una cabaña en temporada alta (junio-agosto)

**Datos previos necesarios**:

-   ID de la cabaña: `1` (Deluxe Cabin)
-   ID del grupo de precio: `2` (Temporada Alta)

**Pasos**:

1. **Crear precio para 2 personas**

```bash
POST /api/v1/cabin-prices-by-guests
{
  "cabin_id": 1,
  "price_group_id": 2,
  "num_guests": 2,
  "price_per_night": 120000
}
```

2. **Crear precio para 3 personas**

```bash
POST /api/v1/cabin-prices-by-guests
{
  "cabin_id": 1,
  "price_group_id": 2,
  "num_guests": 3,
  "price_per_night": 140000
}
```

3. **Crear precio para 4 personas**

```bash
POST /api/v1/cabin-prices-by-guests
{
  "cabin_id": 1,
  "price_group_id": 2,
  "num_guests": 4,
  "price_per_night": 160000
}
```

4. **Verificar los precios creados**

```bash
GET /api/v1/cabin-prices-by-guests/cabin/1?price_group_id=2&sort_by=num_guests
```

---

### Caso 2: Ajustar Precio en Temporada Baja

**Objetivo**: Reducir el precio de una cabaña en temporada baja

**Pasos**:

1. **Obtener el ID del precio a actualizar**

```bash
GET /api/v1/cabin-prices-by-guests?cabin_id=1&price_group_id=1&num_guests=4
```

2. **Actualizar el precio** (supongamos ID = 12)

```bash
PUT /api/v1/cabin-prices-by-guests/12
{
  "price_per_night": 90000
}
```

---

### Caso 3: Eliminar Precios de una Cabaña para una Temporada

**Objetivo**: Limpiar todos los precios de una cabaña para un grupo específico

**Pasos**:

1. **Listar todos los precios**

```bash
GET /api/v1/cabin-prices-by-guests?cabin_id=5&price_group_id=3
```

2. **Eliminar cada precio** (por cada ID)

```bash
DELETE /api/v1/cabin-prices-by-guests/{id}
```

---

### Caso 4: Obtener Información Completa para Mostrar en UI

**Objetivo**: Mostrar una tabla con toda la información necesaria

```bash
GET /api/v1/cabin-prices-by-guests?cabin_id=1&sort_by=num_guests&sort_order=asc&per_page=100
```

En el frontend, construir una tabla como:

| Cabaña | Temporada | Personas | Precio/Noche | Acciones        |
| ------ | --------- | -------- | ------------ | --------------- |
| Deluxe | Alta      | 2        | $120,000     | Editar / Borrar |
| Deluxe | Alta      | 3        | $140,000     | Editar / Borrar |
| Deluxe | Alta      | 4        | $160,000     | Editar / Borrar |

---

## Errores Comunes

### Error 1: Campo Requerido Faltante

**Request:**

```json
{
    "cabin_id": 1,
    "num_guests": 2
}
```

**Respuesta (422):**

```json
{
    "message": "The price group id field is required.",
    "errors": {
        "price_group_id": ["The price group id field is required."]
    }
}
```

**Solución**: Incluir todos los campos requeridos

---

### Error 2: Referencia Inválida

**Request:**

```json
{
    "cabin_id": 999,
    "price_group_id": 2,
    "num_guests": 2,
    "price_per_night": 120000
}
```

**Respuesta (422):**

```json
{
    "message": "The selected cabin id is invalid.",
    "errors": {
        "cabin_id": ["The selected cabin id is invalid."]
    }
}
```

**Solución**: Verificar que la cabaña existe usando el endpoint de cabañas

---

### Error 3: Valor Numérico Inválido

**Request:**

```json
{
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": "dos",
    "price_per_night": 120000
}
```

**Respuesta (422):**

```json
{
    "message": "The num guests must be an integer.",
    "errors": {
        "num_guests": ["The num guests must be an integer."]
    }
}
```

**Solución**: Asegurar que `num_guests` es un número entero

---

### Error 4: Rango de Valores Inválido

**Request:**

```json
{
    "cabin_id": 1,
    "price_group_id": 2,
    "num_guests": 0,
    "price_per_night": 120000
}
```

**Respuesta (422):**

```json
{
    "message": "The num guests must be at least 1.",
    "errors": {
        "num_guests": ["The num guests must be at least 1."]
    }
}
```

**Solución**: num_guests debe estar entre 1 y 255

---

### Error 5: No Autenticado

**Request sin token:**

```bash
GET /api/v1/cabin-prices-by-guests
```

**Respuesta (401):**

```json
{
    "message": "Unauthenticated."
}
```

**Solución**: Incluir header `Authorization: Bearer YOUR_TOKEN`

---

## Integración con Otras Funcionalidades

### Integración con Cálculo de Precios en Reservas

Cuando se crea una reserva, el sistema automáticamente:

1. Obtiene la cabaña de la reserva
2. Obtiene la cantidad de huéspedes
3. Busca el precio específico en `CabinPriceByGuests`
4. Si encuentra coincidencia, usa ese precio
5. Si no, usa el precio del grupo de precio para esa fecha
6. Calcula el total multiplicando noches × precio_por_noche

**En frontend, para cotización:**

```typescript
// Cuando el usuario selecciona:
// - Cabaña: ID 1
// - Fechas: 2025-12-20 a 2025-12-23 (3 noches)
// - Huéspedes: 4

// Enviar solicitud de cotización
POST /api/v1/reservations/quote
{
  "cabin_id": 1,
  "check_in": "2025-12-20",
  "check_out": "2025-12-23",
  "num_guests": 4  // Importante: incluir esta información
}

// Respuesta automáticamente usa los precios correctos:
{
  "data": {
    "cabin_id": 1,
    "check_in": "2025-12-20",
    "check_out": "2025-12-23",
    "nights": 3,
    "total": 480000,  // 160,000 × 3 (usando precio para 4 personas)
    "deposit": 240000,
    "balance": 240000,
    "breakdown": [
      {
        "date": "2025-12-20",
        "price": 160000,
        "price_group": "Temporada Alta"
      },
      {
        "date": "2025-12-21",
        "price": 160000,
        "price_group": "Temporada Alta"
      },
      {
        "date": "2025-12-22",
        "price": 160000,
        "price_group": "Temporada Alta"
      }
    ]
  }
}
```

### Integración con Listado de Cabañas

Cuando se muestra un listado de cabañas, se puede obtener:

1. **Cabaña básica**

```bash
GET /api/v1/cabins
```

2. **Precios de cada cabaña**

```bash
GET /api/v1/cabin-prices-by-guests/cabin/{cabinId}
```

3. **En el frontend mostrar:**

```
Deluxe Cabin (6 personas máx)
━━━━━━━━━━━━━━━━━━━━━━━
Precios por cantidad de huéspedes (Temporada Alta):
  • 2 personas: $120,000
  • 3 personas: $140,000
  • 4 personas: $160,000
```

---

## Ejemplos JavaScript/TypeScript

### Listar Precios

```typescript
async function getCabinPrices(cabinId: number, page: number = 1) {
    const response = await fetch(
        `/api/v1/cabin-prices-by-guests/cabin/${cabinId}?page=${page}&sort_by=num_guests`,
        {
            headers: {
                Authorization: `Bearer ${token}`,
                "Content-Type": "application/json",
            },
        }
    );

    if (!response.ok) throw new Error("Error al obtener precios");
    return response.json();
}
```

### Crear Precio

```typescript
async function createPrice(data: {
    cabin_id: number;
    price_group_id: number;
    num_guests: number;
    price_per_night: number;
}) {
    const response = await fetch("/api/v1/cabin-prices-by-guests", {
        method: "POST",
        headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || "Error al crear precio");
    }

    return response.json();
}
```

### Actualizar Precio

```typescript
async function updatePrice(id: number, data: { price_per_night?: number }) {
    const response = await fetch(`/api/v1/cabin-prices-by-guests/${id}`, {
        method: "PUT",
        headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    });

    if (!response.ok) throw new Error("Error al actualizar precio");
    return response.json();
}
```

### Eliminar Precio

```typescript
async function deletePrice(id: number) {
    const response = await fetch(`/api/v1/cabin-prices-by-guests/${id}`, {
        method: "DELETE",
        headers: {
            Authorization: `Bearer ${token}`,
        },
    });

    if (!response.ok) throw new Error("Error al eliminar precio");
    return response.json();
}
```

---

## Resumen de Información que Debe Enviar el Frontend

### Campos Obligatorios para Crear/Actualizar

| Campo             | Tipo   | Ejemplo  | Notas                        |
| ----------------- | ------ | -------- | ---------------------------- |
| `cabin_id`        | number | `1`      | ID válido de cabaña          |
| `price_group_id`  | number | `2`      | ID válido de grupo de precio |
| `num_guests`      | number | `4`      | Entre 1 y 255                |
| `price_per_night` | number | `160000` | Número decimal ≥ 0           |

### Parámetros de Query para Filtrado

| Parámetro        | Tipo   | Valores                                               | Ejemplo               |
| ---------------- | ------ | ----------------------------------------------------- | --------------------- |
| `page`           | number | 1-∞                                                   | `?page=1`             |
| `per_page`       | number | 1-100                                                 | `?per_page=50`        |
| `sort_by`        | string | id, cabin_id, num_guests, price_per_night, created_at | `?sort_by=num_guests` |
| `sort_order`     | string | asc, desc                                             | `?sort_order=asc`     |
| `cabin_id`       | number | ID válido                                             | `?cabin_id=1`         |
| `price_group_id` | number | ID válido                                             | `?price_group_id=2`   |
| `num_guests`     | number | 1-255                                                 | `?num_guests=4`       |

### Headers Requeridos

Todos los requests deben incluir:

```
Authorization: Bearer {token}
Content-Type: application/json
```

---

## Checklist de Implementación Frontend

-   [ ] Obtener lista de cabañas disponibles
-   [ ] Obtener lista de grupos de precio (temporadas)
-   [ ] Crear interfaz para listar precios por cabaña
-   [ ] Crear formulario para agregar nuevos precios
-   [ ] Crear formulario para editar precios existentes
-   [ ] Implementar eliminación de precios
-   [ ] Validar datos en cliente antes de enviar
-   [ ] Mostrar mensajes de error al usuario
-   [ ] Manejar respuestas exitosas (201, 200)
-   [ ] Implementar paginación si es necesario
-   [ ] Mostrar precios en formato moneda
-   [ ] Integrar con cálculo de reservas (enviar num_guests)
-   [ ] Agregar loading states en operaciones
-   [ ] Implementar confirmación antes de eliminar
