# Reporte del modulo clientes

## Alcance

- Proyecto analizado: `/Users/mateobaravalle/Desktop/Proyectos/Mirador de Luz/api-miradordeluz`
- Tipo de trabajo: revision estatica + ejecucion de tests relevantes
- Restriccion aplicada: no se modifico codigo
- Cobertura del analisis:
  - modulo `clientes` directo
  - integracion minima con `reservations`, porque ese flujo crea, reutiliza y actualiza clientes por DNI

## Archivos clave

### Endpoints

Definidos en `routes/api.php` bajo `auth:sanctum`:

- `GET /api/v1/clients`
- `POST /api/v1/clients`
- `GET /api/v1/clients/{client}`
- `PUT /api/v1/clients/{client}`
- `PATCH /api/v1/clients/{client}`
- `DELETE /api/v1/clients/{client}`
- `GET /api/v1/clients/dni/{dni}`

Referencias:

- `routes/api.php:56`
- `routes/api.php:57`

### Controllers

- `app/Http/Controllers/ClientController.php`
  - `index()`
  - `store()`
  - `show()`
  - `update()`
  - `destroy()`
  - `searchByDni()`

### Services

- `app/Services/ClientService.php`
  - `getClients()`
  - `getClient()`
  - `getClientWithReservations()`
  - `searchByDni()`
  - `createClient()`
  - `updateClient()`
  - `deleteClient()`
- Integracion asociada:
  - `app/Services/ReservationService.php`
    - `resolveClient()`
    - logica de cliente tecnico para bloqueos

### Models

- `app/Models/Client.php`
- Integracion asociada:
  - `app/Models/Reservation.php`

### Requests

- `app/Http/Requests/ClientRequest.php`
- Integracion asociada:
  - `app/Http/Requests/ReservationRequest.php`

### Resources

- `app/Http/Resources/ClientResource.php`

### Otros archivos relevantes

- `app/Traits/BelongsToTenant.php`
- `app/Services/Service.php`
- `app/Http/Requests/ApiRequest.php`
- `app/Traits/ApiResponseFormatter.php`
- `database/migrations/2025_06_01_000003_create_clients_table.php`
- `database/factories/ClientFactory.php`

### Tests asociados

- Directo:
  - `tests/Feature/Api/ClientApiTest.php`
- Indirectos relevantes:
  - `tests/Feature/Api/ReservationApiTest.php`
  - `tests/Unit/Services/ReservationServiceTest.php`

## Funcionalidad actual

### CRUD y consulta

- El modulo permite listar, crear, ver, actualizar y eliminar logicamente clientes.
- El listado usa paginacion y filtros permitidos: `name`, `dni`, `city`, `global`.
- El ordenamiento por defecto viene del controller base: `created_at desc`.
- El detalle del cliente carga reservas y la cabana de cada reserva.
- Existe una busqueda exacta por DNI via endpoint dedicado.

### Representacion API

El `ClientResource` expone:

- `id`
- `name`
- `dni`
- `age`
- `city`
- `phone`
- `email`
- `reservations` cuando la relacion viene cargada
- `reservations_count` cuando existe

### Integracion con reservas

El modulo `clientes` no vive aislado:

- al crear/actualizar reservas, si llega un objeto `client`, el sistema intenta resolver el cliente por `dni`
- si el DNI existe, reutiliza ese cliente y actualiza sus datos no-DNI
- si no existe, crea un nuevo cliente
- si la reserva es un bloqueo, el sistema fuerza un cliente tecnico:
  - nombre: `BLOQUEO DE FECHAS`
  - dni: `00000000`

## Reglas de negocio

### Reglas directas del modulo clientes

- Todas las operaciones estan acotadas al `tenant_id` del usuario autenticado mediante scope global.
- En alta son obligatorios:
  - `name`
  - `dni`
- En update el payload puede ser parcial.
- `dni` debe ser unico por tenant:
  - en validacion
  - en base de datos por indice unico compuesto (`tenant_id`, `dni`)
- `age`:
  - entero
  - minimo `0`
  - maximo `150`
- `email` debe tener formato valido.
- Los campos string se sanitizan antes de validar:
  - trim
  - remocion de caracteres de control
  - colapso de espacios multiples
- La baja es por soft delete, no fisica.
- La busqueda por DNI:
  - usa coincidencia exacta
  - devuelve el primer cliente activo del tenant
  - trae historial de reservas ordenado por `check_in_date desc`

### Reglas indirectas implementadas desde reservas

- Si en una reserva falta `client.dni`, el flujo falla con validacion.
- Si el DNI ya existe, el sistema actualiza el cliente existente con los nuevos datos excepto el DNI.
- Si el DNI no existe, crea un cliente nuevo.
- Si la reserva es un bloqueo:
  - fuerza el cliente tecnico
  - `total_price = 0`
  - `deposit_amount = 0`
  - `balance_amount = 0`
  - `pending_until = null`
- Los bloqueos ocupan disponibilidad de la cabana.
- Un bloqueo puede confirmarse incluso con deposito `0`.

## Testing ejecutado

### Tests corridos

1. `php artisan test tests/Feature/Api/ClientApiTest.php`
2. `php artisan test tests/Unit/Services/ReservationServiceTest.php --filter "test_(create_reservation_without_required_dni|resolve_client_existing|resolve_client_creates_new|create_blocked_reservation_has_zero_price|create_blocked_reservation_uses_block_client|blocked_reservation_blocks_availability|convert_regular_to_blocked_reservation|convert_blocked_to_regular_reservation|blocked_reservation_can_be_confirmed_with_zero_deposit|multiple_blocks_same_cabin|block_with_pending_hours_null|block_doesnt_affect_other_cabins)"`
3. `php artisan test tests/Feature/Api/ReservationApiTest.php --filter "test_(create_reservation_with_block|create_multiple_blocks_same_cabin|block_prevents_normal_reservation|blocks_dont_affect_other_cabins|convert_reservation_to_block|convert_block_to_normal_reservation|block_has_no_pending_hours|block_uses_special_client|blocked_reservation_can_be_confirmed_with_zero_payment)"`

### Resultado real

- `ClientApiTest`: 11 passed, 58 assertions
- `ReservationServiceTest` filtrado: 12 passed, 26 assertions
- `ReservationApiTest` filtrado: 9 passed, 27 assertions
- Total ejecutado: 32 tests passed, 111 assertions

### Errores clave

- No se registraron fallos en los tests ejecutados.

### Cobertura efectiva obtenida

Cubre realmente:

- CRUD basico de clientes
- autenticacion requerida
- busqueda por DNI
- filtro por nombre
- unicidad de DNI en alta
- resolucion de cliente por DNI desde reservas
- creacion de cliente nuevo desde reservas
- cliente tecnico para bloqueos
- impacto de bloqueos sobre disponibilidad

## Hallazgos/Riesgos

### Faltantes de testing

No encontre tests dedicados para:

- `ClientService`
- `ClientRequest`
- `ClientResource`
- modelo `Client`

Tampoco vi cobertura explicita para:

- aislamiento entre tenants en endpoints de clientes
- filtros `dni`, `city`, `global`
- paginacion y ordenamiento
- validaciones de `age` y `email`
- sanitizacion de inputs
- update con DNI duplicado
- respuesta de `show()` con reservas/cabania efectivamente cargadas
- orden del historial en `searchByDni()`

### Riesgos funcionales

**1. Sobreescritura silenciosa de datos de cliente por DNI**

Ubicacion: `app/Services/ReservationService.php:396-413` (`resolveClient`)

Cuando se crea o actualiza una reserva con un objeto `client`, el metodo `resolveClient()` llama a `ClientService::searchByDni()`. Si el DNI ya existe, ejecuta `updateClient()` con todos los campos recibidos excepto `dni` y `tenant_id`, sin ninguna advertencia ni confirmacion. Si dos reservas distintas llegan con el mismo DNI pero datos diferentes (nombre escrito distinto, telefono viejo), el ultimo en llegar sobreescribe al anterior de forma silenciosa.

```php
// ReservationService.php:400-406
$client = $this->clientService->searchByDni($clientData['dni']);
if ($client) {
    $updatableData = Arr::except($clientData, ['dni', 'tenant_id']);
    return $this->clientService->updateClient($client->id, $updatableData);
}
```

**2. Soft delete bloquea el DNI en el indice unico**

Ubicacion: `app/Models/Model.php:14` (`use SoftDeletes` en el modelo base), migracion `database/migrations/2025_06_01_000003_create_clients_table.php`

El modelo base usa `SoftDeletes`. Al eliminar un cliente, el registro queda en la tabla con `deleted_at` no nulo. El indice unico compuesto `(tenant_id, dni)` no excluye registros con soft delete, por lo que ese DNI queda bloqueado. No es posible crear otro cliente con el mismo DNI sin restaurar el registro eliminado o modificar la restriccion de base de datos.

**3. Relacion base `Reservation -> client` sin `withTrashed()` (complementada con relacion historica)**

Ubicacion: `app/Models/Reservation.php`

La relacion activa `client()` se mantiene sin `withTrashed()` por diseño. Para trazabilidad se agrego `clientWithTrashed()` y los servicios operativos principales ya cargan `client` con `withTrashed` de forma selectiva.

Impacto actual:

- mitigado en endpoints operativos de reservas/disponibilidad/resumen;
- pendiente de documentar claramente en que consultas secundarias se usa relacion activa vs historica.

**4. Manejo de errores no uniforme en `ClientController`**

Ubicacion: `app/Http/Controllers/ClientController.php`

Los metodos `index()` (linea ~29) y `store()` (linea ~42) tienen bloque `try/catch` propio que llama a `$this->handleError($e)`. Los metodos `show()`, `update()`, `destroy()` y `searchByDni()` no tienen `try/catch`: cualquier excepcion no controlada (modelo no encontrado, fallo de DB) queda a cargo del handler global de Laravel. Esto puede producir formatos de respuesta de error distintos dependiendo del endpoint que falle.

**5. El tenant_id recibido en `resolveClient()` no se usa para la busqueda**

Ubicacion: `app/Services/ReservationService.php:390` y `app/Services/ClientService.php:48`

`resolveClient()` recibe `$tenantId` como parametro pero solo lo usa al crear un cliente nuevo. La busqueda por DNI delega completamente en el scope global de `BelongsToTenant`:

```php
// BelongsToTenant.php:18-22
static::addGlobalScope('tenant', function (Builder $builder) {
    if (Auth::check() && Auth::user()->tenant_id) {
        $builder->where(...'.tenant_id', Auth::user()->tenant_id);
    }
});
```

El scope solo se activa si `Auth::check()` es verdadero y el usuario tiene `tenant_id`. Si alguna de esas condiciones falla (usuario sin tenant_id, contexto de cola, comando artisan), el scope no se aplica, la busqueda devuelve clientes de cualquier tenant y `resolveClient()` puede reutilizar o sobreescribir un cliente de otro tenant sin error visible.

**6. Crear reserva con DNI de cliente eliminado falla con error 500**

Ubicacion: `app/Services/ReservationService.php:558-570` (`resolveClient`) y `app/Services/ClientService.php:48-57` (`searchByDni`)

Cuando se intenta crear una reserva con el DNI de un cliente que fue eliminado (soft delete), el sistema genera un error 500 de integridad de base de datos:

```sql
SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: clients.tenant_id, clients.dni
```

El flujo ocurre en este orden:

1. `resolveClient()` llama `searchByDni($dni)`
2. `searchByDni()` usa el modelo `Client` que tiene `SoftDeletes`, por lo que automáticamente excluye registros con `deleted_at NOT NULL`
3. Retorna `null` porque el cliente existe pero está eliminado
4. `resolveClient()` interpreta esto como "cliente no existe" e intenta crear uno nuevo
5. La inserción falla porque el indice unico compuesto `(tenant_id, dni)` en la tabla `clients` **incluye todos los registros**, incluso los soft-deleted

```php
// ClientService.php:48-57 - Busca solo clientes activos
public function searchByDni(string $dni): ?Client
{
    return $this->model
        ->where('dni', $dni)
        ->with(['reservations' => function ($query) {
            $query->orderBy('check_in_date', 'desc');
        }, 'reservations.cabin'])
        ->first();  // Retorna null si client->deleted_at is not null
}

// migrations/2025_06_01_000003_create_clients_table.php
$table->unique(['tenant_id', 'dni']);  // No excluye soft-deleted records
```

**Test que demuestra el problema:**

```text
tests/Feature/Api/ReservationApiTest.php::test_create_reservation_with_soft_deleted_client_dni
Response: 500 SQLSTATE[23000]
```

Esta es una barrera operativa real: un usuario no puede reutilizar un DNI de un cliente eliminado para generar nuevas reservas. Debe restaurar el registro manualmente o usar un DNI diferente.

### 7. Validación de DNI única no excluye soft-deleted en endpoints de actualización

Ubicacion: `app/Http/Requests/ClientRequest.php:31-48` (validación de regla única)

La regla de validación para DNI único está bien implementada sintácticamente, pero **no excluye registros soft-deleted**:

```php
// Para POST - crea OK
Rule::unique('clients', 'dni')->where('tenant_id', $tenantId)

// Para PUT/PATCH - actualiza OK
Rule::unique('clients', 'dni')->ignore($clientId)->where('tenant_id', $tenantId)
```

Ambas consultas se ejecutan sobre toda la tabla `clients`, incluyendo registros con `deleted_at IS NOT NULL`. El problema: Si intento actualizar un cliente asignándole un DNI que existía en un cliente ahora eliminado, la validación falla incluso aunque debería permitirse reutilizar ese DNI.

**Escenario concreto:**

1. Creo cliente A con DNI `12345678`
2. Elimino cliente A (soft delete)
3. Intento crear cliente B via `POST /api/v1/clients` con DNI `12345678`
4. Resultado: **422 Validation Error** - "Ya existe un cliente con este DNI"
5. Esperado: Debería permitirse porque el cliente original está eliminado

### 8. Cliente técnico de bloqueos como single point of failure

Ubicacion: `app/Services/ReservationService.php:500-524` (creación de bloqueos), `searchByDni()` y `resolveClient()`

El cliente técnico para bloqueos tiene un nombre y DNI fijos:

```php
// ReservationService.php - al crear un bloqueo
$client = [
    'name' => 'BLOQUEO DE FECHAS',
    'dni' => '00000000',
];
```

Si este cliente es **eliminado accidentalmente** (vía `DELETE /api/v1/clients/{id}`):

1. Cuando se crea un nuevo bloqueo, `resolveClient()` llama `searchByDni('00000000')`
2. La búsqueda devuelve `null` (cliente eliminado)
3. `resolveClient()` intenta crear un cliente nuevo, lo que genera un error 500 de constraint único porque el DNI `00000000` ya existe en la BD (soft-deleted)

**Problema adicional:** No hay protección para impedir la eliminación de este cliente técnico. Un usuario podría eliminarlo sin advertencia.

### 9. Update parcial de cliente puede fallar silenciosamente con soft-delete

Ubicacion: `app/Http/Controllers/ClientController.php:67-75` (método `update`), `app/Services/Service.php:86-91` (método `update` base)

El endpoint `PATCH /api/v1/clients/{id}` permite actualizaciones parciales. Si el cliente está soft-deleted:

1. `ClientRequest` valida solo los campos presentes
2. `ClientService::updateClient()` llama `Service::update()`
3. `Service::update()` usa `findOrFail($id)` que **excluye soft-deleted por el scope global**
4. Lanza `ModelNotFoundException`
5. No hay `try/catch` en el controlador, por lo que Laravel devuelve un error 404 genérico

**Ambiguedad resultante:** El usuario ve error 404, pero no sabe si el cliente nunca existió o si fue eliminado. Además, la operación de actualización se trunca de forma inesperada sin feedback claro.

### 10. Bloqueo no transparente en búsquedas globales de clientes eliminados

Ubicacion: `app/Services/ClientService.php:19-24` (método `getClients`)

El filtro `global` en listado de clientes busca en múltiples campos (`name`, `dni`, `city`, `phone`, `email`). Sin embargo:

1. La búsqueda **automáticamente excluye todos los soft-deleted** por el scope global
2. El usuario no tiene forma de buscar o recuperar clientes eliminados
3. Si un cliente fue eliminado y más tarde el usuario quiere recuperar datos históricos, no hay forma de hacerlo desde la API

**Impacto:** La información no está realmente "eliminada" pero es completamente inaccesible sin acceso directo a la BD o un endpoint especial de restauración que no existe.

### Inconsistencias menores

- La lista filtra `dni` con `LIKE` (`ClientService.php:75`: `where('dni', 'like', "%{$value}%")`), pero el endpoint dedicado `GET /api/v1/clients/dni/{dni}` busca por igualdad exacta (`ClientService.php:48`: `where('dni', $dni)`). Un operador que busque un DNI parcial en el listado obtendra resultados, pero el endpoint dedicado no devolvera nada con el mismo input.
- El comportamiento multitenant depende del scope global del usuario autenticado; sin cobertura especifica de tenant isolation, y dado el riesgo 5 documentado arriba, no puede asumirse como blindado solo por los tests actuales.

## Recomendaciones

- Agregar tests unitarios y feature propios del modulo `clientes`.
- Priorizar cobertura de tenant isolation y de reglas de validacion.
- Definir formalmente la politica de reutilizacion de DNI tras soft delete; considerar excluir registros con `deleted_at` del indice unico o usar un campo de estado separado.
- Revisar si la actualizacion automatica del cliente por DNI desde reservas (`resolveClient`) es deseada o si requiere confirmacion/auditoria antes de sobreescribir datos.
- Mantener `Reservation::client()` como relacion activa y usar `clientWithTrashed()` en endpoints que requieren trazabilidad historica.
- Uniformar el manejo de errores en `ClientController`: agregar `try/catch` en `show()`, `update()`, `destroy()` y `searchByDni()` o mover el manejo al handler global de forma consistente.
- Proteger `resolveClient()` con una verificacion explicita de tenant antes de la busqueda, para no depender exclusivamente del scope global en contextos donde `Auth::check()` puede ser falso (colas, comandos artisan).
- Documentar oficialmente el concepto de cliente tecnico de bloqueo como parte del dominio.
