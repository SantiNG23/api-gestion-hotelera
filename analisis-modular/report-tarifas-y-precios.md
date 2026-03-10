# Reporte del modulo tarifas_y_precios

## Alcance

- Se analizo el proyecto Laravel ubicado en `/Users/mateobaravalle/Desktop/Proyectos/Mirador de Luz/api-miradordeluz` sin modificar codigo.
- El modulo relevante se distribuye entre tarifas base, rangos de fechas, precios por cabaña/cantidad de huespedes y su integracion con reservas.
- Se revisaron rutas, controllers, services, models, requests, resources y tests asociados al modulo y a su dependencia minima de reservas/cotizacion.

## Estado de trabajo actual

Fecha de actualizacion: 2026-03-10

Tras contrastar el reporte original con el codigo actual, se confirma que los siguientes asuntos ya quedaron resueltos en este branch:

1. Resuelto — la seleccion de tarifa usada por `calculate-price`, `quote` y reservas ya reutiliza una logica comun de resolucion en `PriceRangeService`.
2. Resuelto — `GET /price-ranges/applicable-rates` ya no se apoya ciegamente en `price_group.price_per_night = 0` cuando el grupo fue creado via `/complete`.
3. Resuelto — `ReservationQuoteRequest` ya esta alineado con `CalculatePriceRequest` en validaciones clave (`date_format`, `max:255`, tenant-aware `cabin_id`).
4. Resuelto — si falta configuracion tarifaria en flujos normales de reserva/cotizacion, el sistema responde `422`; el precio `0` ya no funciona como fallback silencioso y queda acotado a bloqueos.

Nota operativa: el mayor riesgo del modulo ya no es la divergencia entre algoritmos ni el fallback silencioso a `0`, sino los solapamientos creados desde el CRUD simple y la claridad semantica entre endpoints orientativos y de reserva real.

## Archivos clave

### Rutas y endpoints

Archivo principal: `routes/api.php:63`

Endpoints detectados bajo `/api/v1` y protegidos con `auth:sanctum`:

- `GET|POST /price-groups`
- `GET|PUT|DELETE /price-groups/{id}`
- `POST /price-groups/complete`
- `PUT /price-groups/{id}/complete`
- `GET /price-groups/{id}/complete`
- `GET|POST /price-ranges`
- `GET|PUT|DELETE /price-ranges/{id}`
- `GET /price-ranges/applicable-rates`
- `GET|POST /cabin-prices-by-guests`
- `GET|PUT|DELETE /cabin-prices-by-guests/{id}`
- `GET /cabin-prices-by-guests/cabin/{cabinId}`
- `POST /reservations/calculate-price`
- `POST /reservations/quote`

### Controllers

- `app/Http/Controllers/PriceGroupController.php:14`
- `app/Http/Controllers/PriceRangeController.php:14`
- `app/Http/Controllers/CabinPriceByGuestsController.php:13`
- `app/Http/Controllers/ReservationController.php:18`

### Services

- `app/Services/PriceGroupService.php:16`
- `app/Services/PriceRangeService.php:14`
- `app/Services/CabinPriceByGuestsService.php:11`
- `app/Services/PriceCalculatorService.php:18`
- `app/Services/ReservationService.php:19`

### Models

- `app/Models/PriceGroup.php:11`
- `app/Models/PriceRange.php:11`
- `app/Models/CabinPriceByGuests.php:11`
- `app/Models/Reservation.php:13`

### Requests

- `app/Http/Requests/PriceGroupRequest.php:7`
- `app/Http/Requests/PriceGroupCompleteRequest.php:7`
- `app/Http/Requests/PriceRangeRequest.php:7`
- `app/Http/Requests/PriceRangeApplicableRatesRequest.php:7`
- `app/Http/Requests/CabinPriceByGuestsRequest.php:7`
- `app/Http/Requests/CalculatePriceRequest.php:7`
- `app/Http/Requests/ReservationQuoteRequest.php:7`

### Resources

- `app/Http/Resources/PriceGroupResource.php:7`
- `app/Http/Resources/PriceRangeResource.php:7`
- `app/Http/Resources/CabinPriceByGuestsResource.php:7`

### Tests

- `tests/Feature/Api/PriceGroupApiTest.php:13`
- `tests/Feature/Api/PriceRangeApiTest.php:12`
- `tests/Feature/Api/PriceCalculatorApiTest.php:15`
- `tests/Unit/Services/PriceCalculatorServiceTest.php:17`

## Funcionalidad actual

- `price-groups` ofrece CRUD de grupos de precio con `name`, `price_per_night`, `priority` e `is_default`.
- `price-groups/complete` permite crear o actualizar un grupo completo con cabañas, precios por cantidad de huespedes y rangos de fecha.
- `price-ranges/applicable-rates` devuelve la tarifa aplicable por dia para un rango consultado usando una logica de seleccion consistente.
- `cabin-prices-by-guests` administra el precio exacto por `cabin_id + price_group_id + num_guests`.
- `reservations/calculate-price` y `reservations/quote` reutilizan el mismo calculo base.

## Reglas de negocio vigentes

- Solo puede haber un grupo default por tenant.
- `getApplicableRates()` y la resolucion principal de `PriceRangeService` usan un criterio comun de prioridad.
- El calculo base puede devolver `0` a bajo nivel si no encuentra tarifa resoluble, pero los flujos reservables (`calculate-price`, `quote`, store/update de reservas) lo traducen a `422`.
- El precio `0` queda reservado a bloqueos manuales y no a reservas/cotizaciones normales.
- El breakdown diario incluye fecha, precio y nombre del grupo de precio resuelto.

## Testing ejecutado

Comando ejecutado:

```bash
php artisan test tests/Feature/Api/PriceGroupApiTest.php tests/Feature/Api/PriceRangeApiTest.php tests/Feature/Api/PriceCalculatorApiTest.php tests/Unit/Services/PriceCalculatorServiceTest.php
```

Resultado real:

- 64 tests aprobados
- 299 assertions
- duracion total: 1.85s

## Cobertura observada

- CRUD y flujo `complete` de `price-groups`
- CRUD y `applicable-rates` de `price-ranges`
- endpoint `reservations/calculate-price`
- logica unitaria de `PriceCalculatorService`

## Tests faltantes o no encontrados

- no hay tests dedicados para `CabinPriceByGuestsController`
- no hay tests de aislamiento tenant en precios/cotizacion
- no hay tests de rangos solapados impactando `calculate-price`

## Hallazgos/Riesgos vigentes

### 1. El CRUD simple de `price-ranges` todavia permite crear rangos solapados sin advertencia

Ubicacion: `app/Services/PriceRangeService.php`

La validacion fuerte de solapamientos existe en el flujo `/price-groups/complete`, pero no se aplica con la misma rigurosidad al CRUD simple de `price-ranges`.

Impacto:

- el operador puede introducir conflictos desde un flujo pero no desde otro;
- la resolucion por prioridad evita parte del caos, pero no reemplaza una politica clara de configuracion.

### 2. `applicable-rates` y `calculate-price` siguen teniendo semanticas distintas de rango

Ubicacion: `app/Services/PriceRangeService.php` y `app/Services/PriceCalculatorService.php`

`applicable-rates` opera por dias inclusivos entre `start_date` y `end_date`, mientras que `calculate-price` excluye la noche del `check_out`, como corresponde a una reserva.

Impacto:

- si el frontend consulta ambos endpoints con el mismo rango literal, puede interpretar un dia extra en la vista orientativa;
- la divergencia no es necesariamente un bug, pero si una semantica que conviene documentar o encapsular mejor.

### 3. Mensaje de validacion incorrecto en `CabinPriceByGuestsRequest`

Ubicacion: `app/Http/Requests/CabinPriceByGuestsRequest.php`

La regla exige `min:2`, pero el mensaje personalizado sigue diciendo “al menos 1”.

### 4. Soft delete + indice unico en `cabin_price_by_guests` puede bloquear reutilizacion

Ubicacion: migracion de `cabin_price_by_guests` y modelo `CabinPriceByGuests`

Si se elimina logicamente una combinacion `cabin_id + price_group_id + num_guests`, la restriccion unica puede impedir recrearla sin restaurar o tocar la base manualmente.

## Recomendaciones

1. **Unificar o documentar la semantica de rango entre consulta orientativa y reserva real**
   Especialmente para evitar que el frontend use el mismo `end_date` literal en ambos casos sin ajustar la semantica.

2. **Endurecer el CRUD simple de `price-ranges` frente a solapamientos**
   No necesariamente prohibiendolos siempre, pero al menos detectandolos y comunicandolos.

3. **Corregir el mensaje de `num_guests.min` en `CabinPriceByGuestsRequest`**
   Es una deuda chica, pero confunde al consumidor de la API.

4. **Definir politica para reutilizacion de combinaciones soft-deleted**
   O bien permitir recrearlas limpiamente, o bien documentar/restaurar en vez de reinsertar.
