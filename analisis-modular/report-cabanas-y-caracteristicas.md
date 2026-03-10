# Reporte del modulo `cabanas_y_caracteristicas`

## Alcance

- Proyecto analizado: `/Users/mateobaravalle/Desktop/Proyectos/Mirador de Luz/api-miradordeluz`
- Modulo foco: `cabanas_y_caracteristicas` (cabañas + características)
- Tipo de trabajo: relevamiento funcional y técnico sin modificar código
- Dependencias mínimas auditadas: disponibilidad y cálculo de precio por su dependencia directa sobre cabañas

## Estado de trabajo actual

Fecha de actualizacion: 2026-03-10

Tras contrastar este reporte con el codigo actual, se confirma que los siguientes asuntos del relevamiento original ya quedaron resueltos en este branch:

1. Resuelto — validaciones tenant-aware para `feature_ids`, `cabin_id` y requests relacionados.
2. Resuelto — `available_cabins` ya se serializa con `CabinResource::collection(...)`.
3. Resuelto — `FeatureRequest` ya soporta updates parciales reales.

Nota operativa: la deuda vigente del modulo ya no esta centrada en validaciones tenant-aware basicas, sino en trazabilidad historica y cobertura complementaria.

## Archivos clave

### Endpoints

- `routes/api.php:59` - `Route::apiResource('features', FeatureController::class)`
- `routes/api.php:60` - `Route::apiResource('cabins', CabinController::class)`
- `routes/api.php:83` - `GET /api/v1/availability`
- `routes/api.php:84` - `GET /api/v1/availability/calendar`
- `routes/api.php:85` - `GET /api/v1/availability/{cabin_id}`
- `routes/api.php:74` - `POST /api/v1/reservations/calculate-price`
- `routes/api.php:75` - `POST /api/v1/reservations/quote`

### Controllers

- `app/Http/Controllers/CabinController.php:13`
- `app/Http/Controllers/FeatureController.php:13`
- `app/Http/Controllers/AvailabilityController.php:15`
- `app/Http/Controllers/ReservationController.php:18`

### Services

- `app/Services/CabinService.php:15`
- `app/Services/FeatureService.php:10`
- `app/Services/AvailabilityService.php:17`
- `app/Services/PriceCalculatorService.php:18`

### Models

- `app/Models/Cabin.php:12`
- `app/Models/Feature.php:11`
- `app/Models/Reservation.php:13`

### Requests

- `app/Http/Requests/CabinRequest.php:7`
- `app/Http/Requests/FeatureRequest.php:7`
- `app/Http/Requests/AvailabilityCheckRequest.php:11`
- `app/Http/Requests/AvailabilityShowRequest.php:11`
- `app/Http/Requests/AvailabilityCalendarRequest.php:11`
- `app/Http/Requests/CalculatePriceRequest.php:7`

### Resources

- `app/Http/Resources/CabinResource.php:7`
- `app/Http/Resources/FeatureResource.php:7`

### Tests asociados

- `tests/Feature/Api/CabinApiTest.php:11`
- `tests/Feature/Api/FeatureApiTest.php:10`
- `tests/Unit/Models/CabinTest.php:12`
- `tests/Feature/Api/AvailabilityApiTest.php:13`
- `tests/Unit/Services/AvailabilityServiceTest.php:15`
- `tests/Feature/Api/PriceCalculatorApiTest.php:1`
- `tests/Unit/Services/PriceCalculatorServiceTest.php:17`

## Funcionalidad actual

- CRUD completo de cabañas y features.
- Relación many-to-many `cabin_feature` con sincronización desde `feature_ids`.
- Disponibilidad por rango, por cabaña y calendario operativo.
- Integración directa con pricing y cotización.
- Multitenancy aplicada en modelos raíz y validaciones relevantes de IDs.

## Reglas de negocio vigentes

- `capacity` de cabaña entre `1` y `50`.
- `feature_ids` solo aceptan IDs del tenant autenticado.
- `cabin_id` en disponibilidad/cotización/cálculo de precio solo acepta cabañas del tenant autenticado.
- `PATCH /features/{id}` ya permite updates parciales.
- `available_cabins` reutiliza `CabinResource`, manteniendo el mismo contrato que el resto del CRUD.

## Testing ejecutado

### Comandos ejecutados

```bash
php artisan test tests/Feature/Api/CabinApiTest.php tests/Feature/Api/FeatureApiTest.php tests/Unit/Models/CabinTest.php tests/Feature/Api/AvailabilityApiTest.php tests/Unit/Services/AvailabilityServiceTest.php
php artisan test tests/Feature/Api/PriceCalculatorApiTest.php tests/Unit/Services/PriceCalculatorServiceTest.php
```

### Resultado real

- Bloque 1: 45 tests OK, 188 assertions, `0.63s`
- Bloque 2: 37 tests OK, 151 assertions, `0.54s`
- Total ejecutado: 82 tests OK, 339 assertions, sin fallos

### Cobertura comprobada

- Cabañas:
  - listado
  - alta
  - alta con características
  - validaciones básicas
  - detalle con características
  - update
  - update de características
  - delete
  - filtro por capacidad
- Características:
  - listado
  - alta
  - validación sin nombre
  - update
  - delete
  - filtro por `is_active`
- Disponibilidad:
  - disponibilidad puntual
  - cabañas disponibles
  - reservas pending vencidas/no vencidas
  - rangos bloqueados
  - calendario
- Pricing dependiente:
  - capacidad de cabaña
  - grupos y rangos tarifarios
  - breakdown por noche
  - redondeos
  - validaciones de fechas y huéspedes

### Tests faltantes

- No hay `Feature` model test
- No hay `CabinServiceTest`
- No hay `FeatureServiceTest`
- No hay tests unitarios de `CabinRequest` ni `FeatureRequest`
- No hay tests de `CabinResource` ni `FeatureResource`
- No hay test de `GET /api/v1/features/{id}`
- No hay test de filtros `is_active` en cabañas ni de búsqueda `global`
- No hay test de sync con `feature_ids: []`
- No hay tests explícitos sobre relaciones soft-deleted consumidas desde disponibilidad/reservas

## Hallazgos/Riesgos vigentes

### 1. Relaciones historicas de reservas con `cabin` no preservan soft-deleted

Ubicacion: `app/Models/Reservation.php:62-72`

La relacion `Reservation -> cabin` sigue sin `withTrashed()`. Si una cabaña es eliminada logicamente, las reservas historicas pueden devolver `cabin: null` al cargar la relacion.

Impacto:

- perdida de contexto en historiales y listados;
- riesgo de errores o datos vacios en endpoints que esperan nombre/capacidad de la cabaña;
- trazabilidad historica incompleta.

### 2. `AvailabilityService` asume que `reservation->client` siempre existe

Ubicacion: `app/Services/AvailabilityService.php:71-132`

El calendario usa `reservation->client->name` sin fallback. Si el cliente fue soft-deleted y la relacion devuelve `null`, el endpoint de calendario puede quedar inconsistente o incluso fallar segun el caso.

Impacto:

- el calendario operativo puede mostrar vacios o romperse con reservas historicas;
- el problema no esta en disponibilidad base, sino en la trazabilidad de relaciones blandamente eliminadas.

### 3. La cobertura todavia no protege bien escenarios historicos

Siguen faltando tests para:

- `GET /api/v1/features/{id}`;
- filtros `global` e `is_active` en cabañas;
- detach con `feature_ids: []`;
- escenarios con relaciones soft-deleted consumidas desde reservas/disponibilidad.

## Recomendaciones

1. **Agregar `withTrashed()` donde la trazabilidad historica lo requiera**
   Especialmente en relaciones de `Reservation` hacia `Cabin` y, si aplica, hacia `Client`.

2. **Hacer robusto el calendario frente a relaciones nulas**
   En `AvailabilityService`, usar null-safe access o datos cacheados si el cliente/cabaña originales pueden haber sido eliminados logicamente.

3. **Agregar tests historicos y de cobertura faltante**
   Priorizar `GET /features/{id}`, filtros de cabañas, detach de features y disponibilidad/calendario con entidades soft-deleted.

4. **Documentar que los principales hallazgos tenant-aware del reporte original ya estan resueltos**
   Para evitar reabrir trabajo innecesario en futuras tandas.
