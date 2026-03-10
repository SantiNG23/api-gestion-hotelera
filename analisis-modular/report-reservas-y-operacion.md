# Reporte reservas_y_operacion

## Alcance

- Se analizo el modulo `reservas_y_operacion` del proyecto Laravel en `/Users/mateobaravalle/Desktop/Proyectos/Mirador de Luz/api-miradordeluz` sin modificar codigo.
- El relevamiento incluyo rutas, controllers, services, models, requests, resources, comando asociado, evento/listener, migraciones y tests vinculados a reservas, disponibilidad, resumen diario y pricing dependiente.
- Se ejecutaron tests reales del modulo y de sus dependencias minimas para validar el comportamiento implementado.

## Estado de trabajo actual

Fecha de actualizacion: 2026-03-10

Tras contrastar el reporte original con el codigo actual, se confirma que los siguientes asuntos ya quedaron resueltos en este branch:

1. Resuelto — validaciones tenant-aware sobre IDs relacionados usados desde reservas y disponibilidad.
2. Resuelto — las reservas `is_blocked` canceladas o finalizadas ya no siguen bloqueando disponibilidad.
3. Resuelto — `pending_until` se recalcula al convertir bloqueo <-> reserva normal.
4. Resuelto — el comando `reservations:cancel-expired` ya esta programado en el scheduler.
5. Resuelto — la seleccion de tarifa usada por reservas/cotizaciones ya consume una logica mas consistente desde `PriceRangeService`.
6. Resuelto — `calculate-price`, `quote`, `store` y `update` ya comparten validacion uniforme de capacidad maxima de cabaña.
7. Resuelto — si falta configuracion tarifaria en una reserva/cotizacion normal, el flujo responde `422`; el precio `0` queda reservado a bloqueos.
8. Resuelto — trazabilidad historica de `client`/`cabin` preservada en endpoints operativos clave mediante carga selectiva con `withTrashed`.

Nota operativa: los riesgos vigentes del modulo ya no pasan por `is_blocked`, `pending_until` o scheduler, sino por consistencia de reglas de negocio, trazabilidad historica y señalizacion de errores operativos.

## Archivos clave

- Rutas y endpoints:
  - `routes/api.php`
- Controllers:
  - `app/Http/Controllers/ReservationController.php`
  - `app/Http/Controllers/AvailabilityController.php`
  - `app/Http/Controllers/DailySummaryController.php`
- Services:
  - `app/Services/ReservationService.php`
  - `app/Services/AvailabilityService.php`
  - `app/Services/DailySummaryService.php`
  - `app/Services/PriceCalculatorService.php`
  - `app/Services/PriceRangeService.php`
  - `app/Services/ClientService.php`
- Models:
  - `app/Models/Reservation.php`
  - `app/Models/ReservationPayment.php`
  - `app/Models/ReservationGuest.php`
  - `app/Models/Cabin.php`
  - `app/Models/Client.php`
- Requests:
  - `app/Http/Requests/ReservationRequest.php`
  - `app/Http/Requests/ReservationPaymentRequest.php`
  - `app/Http/Requests/ReservationQuoteRequest.php`
  - `app/Http/Requests/CalculatePriceRequest.php`
  - `app/Http/Requests/AvailabilityCheckRequest.php`
  - `app/Http/Requests/AvailabilityShowRequest.php`
  - `app/Http/Requests/AvailabilityCalendarRequest.php`
  - `app/Http/Requests/DailySummaryRequest.php`
- Resources:
  - `app/Http/Resources/ReservationResource.php`
  - `app/Http/Resources/DailySummaryResource.php`
  - `app/Http/Resources/SimpleCabinResource.php`
- Proceso asociado:
  - `app/Console/Commands/CancelExpiredPendingReservations.php`
- Evento / listener:
  - `app/Events/ReservationCreated.php`
  - `app/Listeners/SendReservationConfirmationEmail.php`
- Tests asociados:
  - `tests/Feature/Api/ReservationApiTest.php`
  - `tests/Feature/Api/AvailabilityApiTest.php`
  - `tests/Feature/Api/DailySummaryApiTest.php`
  - `tests/Feature/Api/PriceCalculatorApiTest.php`
  - `tests/Feature/CancelExpiredPendingReservationsCommandTest.php`
  - `tests/Unit/Services/ReservationServiceTest.php`
  - `tests/Unit/Services/AvailabilityServiceTest.php`
  - `tests/Unit/Services/DailySummaryServiceTest.php`
  - `tests/Unit/Services/PriceCalculatorServiceTest.php`
  - `tests/Unit/Models/ReservationTest.php`

## Funcionalidad actual

- Endpoints implementados del modulo:
  - `POST /api/v1/reservations/calculate-price`
  - `POST /api/v1/reservations/quote`
  - `GET /api/v1/reservations`
  - `POST /api/v1/reservations`
  - `GET /api/v1/reservations/{id}`
  - `PUT/PATCH /api/v1/reservations/{id}`
  - `DELETE /api/v1/reservations/{id}`
  - `POST /api/v1/reservations/{id}/confirm`
  - `POST /api/v1/reservations/{id}/pay-balance`
  - `POST /api/v1/reservations/{id}/check-in`
  - `POST /api/v1/reservations/{id}/check-out`
  - `POST /api/v1/reservations/{id}/cancel`
  - `GET /api/v1/availability`
  - `GET /api/v1/availability/{cabin_id}`
  - `GET /api/v1/availability/calendar`
  - `GET /api/v1/daily-summary`
- Todo el modulo opera bajo `auth:sanctum`.
- Los modelos raiz usan scope global por tenant mediante `BelongsToTenant`.
- Las reservas permiten flujo completo de pendiente -> confirmada -> check-in -> check-out, mas cancelacion y bloqueos manuales.

## Reglas de negocio vigentes

- Estados implementados:
  - `pending_confirmation`
  - `confirmed`
  - `checked_in`
  - `finished`
  - `cancelled`
- Creacion:
  - valida disponibilidad antes de crear;
  - una reserva bloqueada se crea con cliente tecnico y montos en `0`;
  - una reserva normal genera `pending_until = now + pending_hours`;
  - una reserva bloqueada genera `pending_until = null`.
- Actualizacion:
  - si cambian fechas/cabaña/`is_blocked`/huespedes, recalcula disponibilidad y montos;
  - si cambia `is_blocked`, tambien normaliza `pending_until`.
- Disponibilidad:
  - bloquean `pending_confirmation` no vencidas, `confirmed`, `checked_in` y bloqueos activos;
  - reservas canceladas o finalizadas ya no bloquean aunque hayan sido bloqueos.
- Pricing:
  - `calculate-price`, `quote`, `store` y `update` comparten la misma validacion de capacidad maxima de cabaña;
  - si falta configuracion tarifaria exacta en una reserva/cotizacion normal, el flujo responde `422`;
  - el precio `0` queda reservado a bloqueos manuales.

## Testing ejecutado

- Comando ejecutado:
  - `php artisan test tests/Feature/Api/ReservationApiTest.php tests/Feature/Api/AvailabilityApiTest.php tests/Feature/Api/DailySummaryApiTest.php tests/Feature/Api/PriceCalculatorApiTest.php tests/Feature/CancelExpiredPendingReservationsCommandTest.php tests/Unit/Services/ReservationServiceTest.php tests/Unit/Services/AvailabilityServiceTest.php tests/Unit/Services/DailySummaryServiceTest.php tests/Unit/Services/PriceCalculatorServiceTest.php tests/Unit/Models/ReservationTest.php`
- Resultado real:
  - `177` tests passed
  - `562` assertions
  - duracion `1.80s`
  - sin fallos

## Cobertura funcional comprobada

- flujo completo de reserva
- disponibilidad
- resumen diario
- calculo de precio
- cancelacion automatica de pendientes vencidas
- comportamiento base del modelo `Reservation`

## Tests faltantes detectados

- no hay prueba HTTP directa para `POST /api/v1/reservations/{id}/cancel`
- el test llamado “cancel reservation success” sigue probando `DELETE /reservations/{id}`, no `POST /cancel`
- no hay prueba directa de `daily-summary` en escenario con `client`/`cabin` soft-deleted

## Hallazgos/Riesgos vigentes

### 1. Trazabilidad historica principal: mitigada con estrategia selectiva

Ubicacion: `app/Models/Reservation.php`, `app/Services/ReservationService.php`, `app/Services/AvailabilityService.php`, `app/Services/DailySummaryService.php`, `app/Services/ClientService.php`

Se agregaron relaciones historicas explicitas (`clientWithTrashed`, `cabinWithTrashed`) y los servicios operativos principales ahora cargan `client`/`cabin` con `withTrashed` donde corresponde. En calendario tambien se aplico fallback null-safe para `client_name`.

Estado:

- mitigado para endpoints operativos principales de reservas, disponibilidad y resumen diario;
- pendiente de documentar explicitamente en que consultas secundarias debe usarse relacion activa vs historica.

### 2. `expiring_pending` mezcla alertas operativas de distinta naturaleza

Ubicacion: `app/Services/DailySummaryService.php`

La misma lista combina:

- reservas pendientes que vencen hoy por falta de seña;
- reservas de hoy sin saldo cobrado.

Ambos casos requieren acciones operativas distintas pero hoy llegan agrupados.

### 3. `SendReservationConfirmationEmail` sigue siendo un placeholder

Ubicacion: `app/Listeners/SendReservationConfirmationEmail.php`

El listener se encola pero solo registra un log. No produce el efecto que su nombre sugiere.

### 4. `cancel` y `delete` siguen siendo semanticamente distintos pero la cobertura/documentacion no lo reflejan bien

Ubicacion: `app/Http/Controllers/ReservationController.php` y tests del modulo.

El dominio distingue entre cancelar por estado y eliminar logicamente el registro, pero la suite y parte de la documentacion historica mezclan ambos conceptos.

## Recomendaciones

1. **Formalizar politica de uso de relaciones activas vs historicas**
   Documentar en que endpoints se usa carga historica (`withTrashed`) y donde debe mantenerse relacion activa.

2. **Separar alertas del resumen diario**
   Dividir `expiring_pending` en categorias operativas distintas si el frontend necesita acciones diferentes.

3. **Implementar o desactivar el listener de confirmacion**
   Si no va a enviar correos reales, conviene documentarlo o renombrarlo para evitar falsas expectativas.

4. **Alinear tests y documentacion con la semantica real de `cancel` vs `delete`**
   Especialmente agregando pruebas HTTP directas para `POST /reservations/{id}/cancel`.
