# Backend Docs para Frontend

Documento de referencia para integrar el frontend contra la API de `api-miradordeluz`.

## Base URL y versionado

- Prefijo API: `/api/v1`
- Content-Type esperado: `application/json`
- Accept recomendado: `application/json`
- Autenticacion privada: `Authorization: Bearer {token}`

## Convenciones generales

### Formato de respuesta exitosa

Respuesta estandar para recursos individuales:

```json
{
  "success": true,
  "message": "Operacion exitosa",
  "data": {}
}
```

Respuesta estandar para colecciones paginadas:

```json
{
  "success": true,
  "message": "Operacion exitosa",
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1,
    "from": 1,
    "to": 1
  },
  "links": {
    "first": "https://...",
    "last": "https://...",
    "prev": null,
    "next": null
  }
}
```

### Formato de error

```json
{
  "success": false,
  "message": "Error de validacion",
  "errors": {
    "field": ["Mensaje de error"]
  }
}
```

### Codigos frecuentes

- `200 OK`
- `201 Created`
- `401 Unauthorized`
- `403 Forbidden`
- `404 Not Found`
- `422 Unprocessable Entity`
- `429 Too Many Requests`

### Reglas de integracion a no romper

- El frontend NO debe enviar `tenant_id` en login/discover; el flujo usa `tenant_slug`.
- Las rutas autenticadas resuelven tenant por usuario/token en backend.
- Los montos monetarios salen serializados como float (`300.0`, no `300`).
- Fechas de negocio suelen salir como `Y-m-d`.
- Fechas con timestamp suelen salir en ISO8601 o `Y-m-d H:i:s`, segun el recurso.

## Flujo de autenticacion

### 1. Descubrir acceso por email

`POST /api/v1/auth/discover`

Request:

```json
{
  "email": "user@example.com"
}
```

Response (`mode = not_found | single_tenant | multi_tenant`):

```json
{
  "success": true,
  "message": "Selecciona un tenant para continuar.",
  "data": {
    "mode": "multi_tenant",
    "email": "user@example.com",
    "tenants": [
      {
        "slug": "mirador-centro",
        "name": "Mirador Centro"
      }
    ]
  }
}
```

Uso esperado:

- `not_found`: mostrar error funcional de acceso no encontrado.
- `single_tenant`: el frontend igual recibe `tenants`; puede auto-seleccionar el unico `slug`.
- `multi_tenant`: pedir seleccion de cuenta antes del login.

### 2. Login

`POST /api/v1/auth/login`

Request:

```json
{
  "email": "user@example.com",
  "password": "secret",
  "tenant_slug": "mirador-centro"
}
```

Response:

```json
{
  "success": true,
  "message": "Usuario autenticado exitosamente",
  "data": {
    "token": "1|sanctum-token",
    "user": {
      "id": 10,
      "name": "Juan Perez",
      "email": "user@example.com",
      "tenant": {
        "id": 2,
        "slug": "mirador-centro",
        "name": "Mirador Centro"
      },
      "created_at": "2026-03-01T10:00:00.000000Z",
      "updated_at": "2026-03-01T10:00:00.000000Z"
    },
    "tenant": {
      "id": 2,
      "slug": "mirador-centro",
      "name": "Mirador Centro"
    }
  }
}
```

Errores funcionales a contemplar:

- Credenciales invalidas: `422` con `errors.code = ["invalid_credentials"]`
- Tenant faltante/invalido: `422` con `errors.code = ["tenant_required"]`
- Tenant inactivo: `422` con `errors.code = ["inactive_tenant"]`

### 3. Bootstrap de sesion

`GET /api/v1/auth`

- Requiere Bearer token.
- Devuelve el usuario autenticado con `tenant` cargado.
- Sirve para hidratar session store al refrescar la app.

### 4. Logout

`DELETE /api/v1/auth`

- Requiere Bearer token.
- Revoca todos los tokens del usuario.

## Headers recomendados para el frontend

### Publicos

```http
Content-Type: application/json
Accept: application/json
```

### Autenticados

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

## Mapa rapido de endpoints

### Auth y perfil

| Metodo | Ruta | Auth | Uso principal |
| --- | --- | --- | --- |
| POST | `/auth/discover` | No | Resolver tenants disponibles para un email |
| POST | `/auth/login` | No | Login con `tenant_slug` |
| GET | `/auth` | Si | Bootstrap de sesion actual |
| DELETE | `/auth` | Si | Logout |
| GET | `/users/profile` | Si | Obtener perfil |
| PUT | `/users/profile` | Si | Actualizar perfil |
| PUT | `/users/password` | Si | Cambiar password |

### Clientes

| Metodo | Ruta | Auth | Uso principal |
| --- | --- | --- | --- |
| GET | `/clients` | Si | Listado paginado |
| POST | `/clients` | Si | Alta |
| GET | `/clients/{id}` | Si | Detalle |
| PUT/PATCH | `/clients/{id}` | Si | Edicion |
| DELETE | `/clients/{id}` | Si | Baja |
| GET | `/clients/dni/{dni}` | Si | Busqueda por DNI |

Shape habitual de `client`:

```json
{
  "id": 1,
  "name": "Juan Perez",
  "dni": "30111222",
  "age": 35,
  "city": "Posadas",
  "phone": "+543764000000",
  "email": "juan@example.com",
  "reservations_count": 3
}
```

### Cabanas y features

| Metodo | Ruta | Auth | Uso principal |
| --- | --- | --- | --- |
| GET/POST | `/features` | Si | CRUD features |
| GET/PUT/PATCH/DELETE | `/features/{id}` | Si | CRUD feature puntual |
| GET/POST | `/cabins` | Si | CRUD cabanas |
| GET/PUT/PATCH/DELETE | `/cabins/{id}` | Si | CRUD cabana puntual |

Shape habitual de `feature`:

```json
{
  "id": 1,
  "name": "Parrilla",
  "icon": "flame",
  "is_active": true
}
```

Shape habitual de `cabin`:

```json
{
  "id": 1,
  "name": "Cabana 1",
  "description": "Vista al monte",
  "capacity": 4,
  "is_active": true,
  "features": []
}
```

### Tarifas

| Metodo | Ruta | Auth | Uso principal |
| --- | --- | --- | --- |
| POST | `/price-groups/complete` | Si | Alta completa de grupo con detalle asociado |
| PUT | `/price-groups/{id}/complete` | Si | Edicion completa |
| GET | `/price-groups/{id}/complete` | Si | Detalle completo |
| GET/POST | `/price-groups` | Si | CRUD grupos de precio |
| GET/PUT/PATCH/DELETE | `/price-groups/{id}` | Si | CRUD grupo puntual |
| GET | `/price-ranges/applicable-rates` | Si | Buscar tarifas aplicables |
| GET/POST | `/price-ranges` | Si | CRUD rangos |
| GET/PUT/PATCH/DELETE | `/price-ranges/{id}` | Si | CRUD rango puntual |
| GET | `/cabin-prices-by-guests/cabin/{cabinId}` | Si | Tarifas por cabana/cantidad de huespedes |
| GET/POST | `/cabin-prices-by-guests` | Si | CRUD tarifas por huesped |
| GET/PUT/PATCH/DELETE | `/cabin-prices-by-guests/{id}` | Si | CRUD tarifa puntual |

Shape habitual de `price_group`:

```json
{
  "id": 1,
  "name": "Temporada alta",
  "price_per_night": 300.0,
  "priority": 10,
  "is_default": false
}
```

Shape habitual de `price_range`:

```json
{
  "id": 1,
  "price_group_id": 1,
  "start_date": "2026-01-01",
  "end_date": "2026-02-28",
  "price_group": {
    "id": 1,
    "name": "Temporada alta",
    "price_per_night": 300.0,
    "priority": 10,
    "is_default": false
  }
}
```

### Reservas

| Metodo | Ruta | Auth | Uso principal |
| --- | --- | --- | --- |
| POST | `/reservations/calculate-price` | Si | Calculo de precio |
| POST | `/reservations/quote` | Si | Cotizacion previa |
| GET/POST | `/reservations` | Si | CRUD reservas |
| GET/PUT/PATCH/DELETE | `/reservations/{reservation}` | Si | CRUD reserva puntual |
| POST | `/reservations/{reservation}/confirm` | Si | Confirmar |
| POST | `/reservations/{reservation}/pay-balance` | Si | Registrar pago saldo |
| POST | `/reservations/{reservation}/check-in` | Si | Check-in |
| POST | `/reservations/{reservation}/check-out` | Si | Check-out |
| POST | `/reservations/{reservation}/cancel` | Si | Cancelar |

Shape habitual de `reservation`:

```json
{
  "id": 1,
  "client_id": 2,
  "cabin_id": 3,
  "num_guests": 4,
  "check_in_date": "2026-04-10",
  "check_out_date": "2026-04-15",
  "nights": 5,
  "total_price": 1500.0,
  "deposit_amount": 500.0,
  "balance_amount": 1000.0,
  "status": "pending",
  "is_blocked": false,
  "pending_until": "2026-04-01 12:00:00",
  "notes": null,
  "client": null,
  "cabin": null,
  "guests": [],
  "payments": []
}
```

### Disponibilidad

| Metodo | Ruta | Auth | Uso principal |
| --- | --- | --- | --- |
| GET | `/availability` | Si | Chequeo de disponibilidad |
| GET | `/availability/{cabin_id}` | Si | Disponibilidad puntual por cabana |
| GET | `/availability/calendar` | Si | Calendario consolidado |

### Reportes y resumen

| Metodo | Ruta | Auth | Uso principal |
| --- | --- | --- | --- |
| GET | `/daily-summary` | Si | Resumen diario |
| GET | `/reports/guests` | Si | Reporte de huespedes |
| GET | `/reports/history-dni` | Si | Historial por DNI |
| GET | `/reports/occupancy` | Si | Ocupacion |
| GET | `/reports/reservations` | Si | Reservas |
| GET | `/reports/summary` | Si | Resumen general |

## Observabilidad de logs frontend (`warn` / `error`)

### Endpoint

`POST /api/v1/observability/frontend-logs`

### Autenticacion

- Requiere `Authorization: Bearer {token}`.
- Multi-tenant por usuario autenticado (`tenant_id` se resuelve y enriquece en backend).

### Headers requeridos

- `Content-Type: application/json`
- `Accept: application/json`
- `Authorization: Bearer ...`

### Request body

```json
{
  "timestamp": "2026-03-06T20:45:12.431Z",
  "level": "warn",
  "scope": "app",
  "context": ["api", "response-interceptor"],
  "event_name": "api.response.429",
  "meta": {
    "status": 429,
    "url": "/reservations",
    "method": "get"
  },
  "args": ["Demasiadas solicitudes"]
}
```

### Validaciones

- `timestamp`: requerido, ISO8601 estricto.
- `level`: requerido, enum `warn|error`.
- `scope`: requerido, string, max 100.
- `context`: opcional, array max 10, cada item string max 100.
- `event_name`: opcional, string max 150.
- `meta`: opcional, debe ser objeto JSON, no array/lista.
- `args`: opcional, array max 20.
- Debe llegar al menos uno entre `event_name` o `args` con contenido.
- Tamano maximo de payload: `32KB`.

### Enriquecimiento backend

Cada evento se persiste con:

- `tenant_id`
- `user_id`
- `ip`
- `user_agent`
- `request_id` (si viene en `X-Request-Id` / `X-Request-ID` o atributo de request)
- `occurred_at` (desde `timestamp`)
- `ingested_at` (hora servidor)

### Sanitizacion

Se redactan claves sensibles en `meta` y `args` de forma recursiva con `[REDACTED]`:

- `authorization`
- `token`
- `password`
- `access_token`
- `refresh_token`
- `cookie`
- `secret`
- `api_key`

### Rate limiting

- `120 requests/minuto` por usuario autenticado.
- Burst de `20 requests/10 segundos`.
- Si excede, responde `429` con contrato estandar de error API.

### Response exitosa

```json
{
  "success": true,
  "message": "Log de observabilidad registrado exitosamente",
  "data": {
    "id": "uuid",
    "ingested_at": "2026-03-06T20:45:12.431Z"
  }
}
```

## Recomendaciones para el frontend

- Centralizar un API client que siempre agregue `Accept: application/json`.
- Resolver auth en dos pasos: `discover` -> seleccion de cuenta -> `login`.
- Persistir `token`, `user` y `tenant` del login; al refrescar, rehidratar con `GET /auth`.
- Tratar `422` como error funcional de formulario y leer `errors` por campo.
- Tratar `401` como sesion vencida y limpiar credenciales locales.
