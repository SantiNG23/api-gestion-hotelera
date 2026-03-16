# Reporte: acceso_y_perfil_de_usuario

## Alcance

- Proyecto revisado: `/Users/mateobaravalle/Desktop/Proyectos/Mirador de Luz/api-miradordeluz`.
- Modulo analizado: autenticacion, perfil y password.
- Revision realizada sin modificar codigo.
- Se analizaron rutas, controladores, servicios, modelo de usuario, requests, resources, eventos/listeners, middlewares API y tests asociados.
- Se ejecutaron unicamente los tests relevantes del modulo y sus dependencias minimas.

## Estado de trabajo actual

Fecha de actualizacion: 2026-03-10

Tras contrastar este reporte con el codigo actual, se confirma que los siguientes asuntos ya quedaron resueltos en este branch:

1. Resuelto — `ValidateApiHeaders` ahora acepta media types JSON validos con parametros y variantes `+json`.
2. Resuelto — el cambio de password invalida los tokens activos del usuario.
3. Resuelto — `User` ya usa `BelongsToTenant` y `AuthService` ya resuelve `tenant_id` para autenticacion/registro.
4. Resuelto — la unicidad del email ya fue migrada a nivel `(tenant_id, email)`.
5. Resuelto — existe `app/Http/Resources/UserResource.php` y auth/perfil ya usan un contrato explicito de serializacion del usuario.
6. Resuelto — la ruta legacy `/api/user` fue eliminada; el acceso al usuario autenticado queda normalizado bajo `/api/v1`.

Nota operativa: este modulo contenia varios hallazgos historicos que quedaron desactualizados. Las secciones siguientes fueron ajustadas para dejar solo deuda vigente o decisiones de diseño aun abiertas.

Sin cambios directos en este modulo en la ultima tanda de implementacion (trazabilidad historica), por lo que este reporte se mantiene funcionalmente vigente.

## Archivos clave

### Endpoints

| Metodo | Ruta | Proteccion | Implementacion |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth` | Publica | `routes/api.php:39`, `app/Http/Controllers/AuthController.php:21` |
| `GET` | `/api/v1/auth` | `auth:sanctum` | `routes/api.php:40`, `app/Http/Controllers/AuthController.php:46` |
| `DELETE` | `/api/v1/auth` | `auth:sanctum` | `routes/api.php:41`, `app/Http/Controllers/AuthController.php:36` |
| `GET` | `/api/v1/users/profile` | `auth:sanctum` | `routes/api.php:45`, `app/Http/Controllers/UserController.php:21` |
| `PUT` | `/api/v1/users/profile` | `auth:sanctum` | `routes/api.php:46`, `app/Http/Controllers/UserController.php:32` |
| `PUT` | `/api/v1/users/password` | `auth:sanctum` | `routes/api.php:47`, `app/Http/Controllers/UserController.php:45` |

### Controllers

- `app/Http/Controllers/AuthController.php:12`
- `app/Http/Controllers/UserController.php:12`

### Services

- `app/Services/AuthService.php:11`
- `app/Services/UserService.php:10`
- Dependencia base: `app/Services/Service.php:20`

### Models

- `app/Models/User.php:13`
- Relacion multi-tenant indirecta: `app/Models/Tenant.php:10`

### Requests

- `app/Http/Requests/AuthRequest.php:9`
- `app/Http/Requests/UserRequest.php:10`
- Base compartida: `app/Http/Requests/ApiRequest.php:11`

### Resources / Formato de respuesta

- `app/Http/Resources/AuthResource.php:7`
- `app/Http/Resources/UserResource.php:7`
- `app/Http/Resources/ApiResource.php:9`
- Formateo y resolucion de resources: `app/Traits/ApiResponseFormatter.php:29`

### Eventos y listeners

- `app/Events/UserRegistered.php:12`
- `app/Listeners/SendWelcomeEmail.php:12`
- `app/Listeners/CreateInitialUserSettings.php:11`
- Registro de listeners: `app/Providers/EventServiceProvider.php:24`

### Middleware y bootstrap API

- `bootstrap/app.php:20`
- `app/Http/Middleware/ValidateApiHeaders.php:11`
- `app/Http/Middleware/ApiRateLimiter.php:12`

### Tests asociados

- `tests/Feature/Auth/AuthTest.php:12`
- `tests/Feature/Api/UserApiTest.php:12`
- `tests/Unit/Services/AuthServiceTest.php:14`
- `tests/Unit/Models/UserTest.php:12`
- `tests/Feature/Events/UserRegisteredTest.php:16`

## Funcionalidad actual

- `POST /api/v1/auth` funciona como endpoint dual:
  - si el email no existe, registra usuario
  - si el email existe, intenta autenticarlo
- El registro/autenticacion devuelve token Sanctum en la respuesta.
- `GET /api/v1/auth` devuelve el usuario autenticado.
- `DELETE /api/v1/auth` elimina todos los tokens del usuario autenticado.
- `GET /api/v1/users/profile` devuelve el perfil del usuario actual.
- `PUT /api/v1/users/profile` actualiza nombre y/o email del usuario actual.
- `PUT /api/v1/users/password` cambia la password del usuario autenticado validando password actual.
- `POST /api/v1/auth` serializa usuario con `AuthResource` y expone `token` solo en login/registro.
- `GET /api/v1/auth` y `GET/PUT /api/v1/users/profile` serializan usuario con `UserResource` sin exponer `token`.
- Las respuestas exitosas usan formato general `{ success, message, data }`.
- Los errores de validacion usan formato `{ success, message, errors }`.
- Todas las requests API saneadas por `ApiRequest` hacen trim, remueven caracteres de control y colapsan espacios multiples.
- Todas las rutas del modulo pasan por middleware API global:
  - validacion estricta de headers JSON
  - rate limit general
  - manejo uniforme de excepciones JSON

## Reglas de negocio

- Auth decide entre login y registro segun exista el email en base de datos.
- Para registro:
  - `name` es obligatorio
  - `name` debe tener minimo 3 caracteres
  - `name` solo admite letras y espacios
  - `email` es obligatorio, valido y maximo 255
  - `password` es obligatoria, minima 8, confirmada y con complejidad obligatoria
- Para login:
  - se valida `email` y `password`
  - si las credenciales no coinciden, se responde `422` con error en `email`
- Para perfil:
  - `name` es obligatorio en actualizacion de perfil
  - `email` no es obligatorio
  - `email`, si viene, debe ser unico dentro del tenant ignorando al usuario autenticado
- Para cambio de password:
  - `current_password` es obligatoria y debe coincidir con la actual
  - la nueva password debe cumplir la misma complejidad
  - la nueva password debe venir confirmada
- Logout revoca todos los tokens del usuario, no solo el token usado en la request.
- Registro dispara el evento `UserRegistered`.
- Los listeners registrados para `UserRegistered` hoy no generan efectos funcionales reales:
  - `SendWelcomeEmail` no envia correo; solo hace `Mail::fake()`
  - `CreateInitialUserSettings` no implementa ninguna accion
- El modulo no implementa recuperacion de password ni reseteo via email.
- El modulo no implementa verificacion de email en el flujo de registro actual.
- El middleware `ValidateApiHeaders` exige:
  - un media type JSON valido en `Accept` (`application/json` o variantes `+json`, incluso con parametros)
  - para `POST`, `PUT`, `PATCH`, tambien un media type JSON valido en `Content-Type`
- El middleware `ApiRateLimiter` limita a 60 requests por minuto por usuario o IP.

## Testing ejecutado

Se ejecutaron los siguientes comandos y estos fueron los resultados reales:

| Comando | Resultado |
| --- | --- |
| `php artisan test tests/Feature/Auth/AuthTest.php tests/Feature/Api/UserApiTest.php tests/Unit/Services/AuthServiceTest.php tests/Unit/Models/UserTest.php tests/Feature/Events/UserRegisteredTest.php` | 38 tests OK, 136 assertions, 0.44s |

### Total ejecutado

- 38 tests OK
- 136 assertions
- Duracion aproximada: 0.44s

### Que si cubren los tests actuales

- Registro exitoso.
- Login exitoso.
- Logout exitoso.
- Consulta de usuario autenticado.
- Errores de validacion basicos en auth.
- Consulta y actualizacion de perfil.
- Cambio de password con caso exitoso y error de password actual.
- Revocacion de tokens tras cambio de password y login posterior con la nueva credencial.
- Contrato de serializacion sin `token` en endpoints de lectura de usuario.
- Metodos basicos de `AuthService`.
- Operaciones basicas del modelo `User`.
- Despacho y wiring del evento `UserRegistered`.

### Que no cubren bien o no cubren

- No hay tests directos para `UserService`.
- No hay tests directos para `AuthRequest` y `UserRequest` como unidades.
- No hay tests de `ValidateApiHeaders`.
- No hay tests de `ApiRateLimiter`.
- No hay tests para email duplicado al actualizar perfil.
- No hay tests de acceso no autenticado a `/api/v1/users/profile` y `/api/v1/users/password`.
- No hay tests de recuperacion de password porque ese flujo no existe.
- No hay tests de side effects reales del registro; ademas el `TestCase` fakea eventos, mail y queue por defecto.

## Hallazgos/Riesgos

### 1. Listeners del evento `UserRegistered` siguen siendo placeholders sin efecto funcional real

Ubicacion: `app/Listeners/SendWelcomeEmail.php:29-34` y `app/Listeners/CreateInitialUserSettings.php:28-32`

Ambos listeners implementan `ShouldQueue` (se encolan) pero sus metodos `handle()` no realizan ninguna accion funcional. `SendWelcomeEmail` llama a `Mail::fake()` dentro de codigo de produccion, lo que activa el fake global de mail en runtime si la cola lo procesa fuera de tests. `CreateInitialUserSettings` tiene el cuerpo vacio.

```php
// SendWelcomeEmail.php:29-34
public function handle(UserRegistered $event): void
{
    // Aquí iría la lógica para enviar el email de bienvenida
    // Por ahora solo simulamos el envío
    Mail::fake();
}
```

```php
// CreateInitialUserSettings.php:28-32
public function handle(UserRegistered $event): void
{
    // Aquí iría la lógica para crear las configuraciones iniciales del usuario
    // Por ejemplo, preferencias de notificación, tema, etc.
}
```

El cableado en `EventServiceProvider` esta correcto (`app/Providers/EventServiceProvider.php:28-31`), por lo que el evento se despacha pero sin efecto util. `SendWelcomeEmail` incluso llama `Mail::fake()` dentro de codigo productivo, lo cual no deberia permanecer asi.

---

### 2. El endpoint dual `POST /auth` sigue mezclando login y registro en un mismo contrato

Ubicacion: `app/Http/Requests/AuthRequest.php:14-37` y `app/Services/AuthService.php:38-57`

La decision de aplicar reglas de login o de registro sigue dependiendo del estado de la BD y del `tenant_id` provisto. Aunque la implementacion multi-tenant mejoro mucho respecto de versiones anteriores, el endpoint sigue combinando dos intenciones distintas en una sola ruta.

```php
// AuthRequest.php
if (! $existingUserQuery->exists()) {
    $rules = array_merge($rules, [
        'name' => 'required|string|min:3|max:255|regex:/^[\p{L}\s]+$/u',
        'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]+$/|confirmed',
    ]);
}
```

Esto no es un bug critico por si solo, pero si una deuda de diseño: complica la semantica del contrato, obliga a mirar la BD para decidir la intencion del request y vuelve mas confusa la evolucion futura del flujo de onboarding.

---

### 3. Credenciales invalidas siguen respondiendo `422` en vez de `401`

Ubicacion: `app/Services/AuthService.php:49-53` (`authenticate`)

Cuando las credenciales no coinciden, `authenticate()` lanza una `ValidationException` con error en `email`, lo que se traduce en un `422 Unprocessable Entity` en lugar del semanticamente mas natural `401 Unauthorized`.

```php
// AuthService.php
throw ValidationException::withMessages([
    'email' => ['Las credenciales proporcionadas son incorrectas.'],
]);
```

Esto hoy parece una decision de UX/API mas que una falla funcional urgente, pero conviene documentarlo o revisarlo si se busca una API mas estandar.

---

### 4. No hay flujo real de recuperacion o reset de password

Ubicacion: ausencia de rutas/controladores/servicios especificos en `routes/api.php` y modulo de auth.

El modulo cubre login/registro, perfil, logout y cambio de password autenticado, pero no implementa recovery/reset via email o token temporal.

Esto no rompe la operacion basica, pero si limita madurez del modulo de acceso.

---

### 5. Los tests de listeners siguen verificando wiring, no efectos reales

Ubicacion: `tests/Feature/Events/UserRegisteredTest.php:64-94`

Los tests `it_handles_welcome_email_sending` y `it_handles_initial_settings_creation` invocan los listeners directamente y luego solo afirman que el usuario sigue existiendo en base de datos. No validan side effects reales. Los tests siguen pasando incluso con listeners vacios.

```php
// UserRegisteredTest.php:64-78
public function it_handles_welcome_email_sending()
{
    $listener = new SendWelcomeEmail;
    $user = User::factory()->create();
    $event = new UserRegistered($user);

    $listener->handle($event);

    // Verificamos que el usuario existe y tiene los datos correctos
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => $user->email,
    ]);
}
```

---

## Recomendaciones

- **Riesgo 1** — Implementar o eliminar los listeners `SendWelcomeEmail` y `CreateInitialUserSettings`. Si permanecen, remover `Mail::fake()` del codigo productivo y reescribir los tests para verificar efectos reales.

- **Riesgo 2** — Si se desea un contrato mas claro, separar login y registro en endpoints distintos (`POST /api/v1/auth/login` y `POST /api/v1/auth/register`). Si se mantiene el endpoint dual, documentar explicitamente la semantica y sus implicancias multi-tenant.

- **Riesgo 3** — Evaluar si conviene responder `401` en credenciales invalidas usando `AuthenticationException`, o al menos documentar que el API hoy trata ese caso como error de validacion de formulario (`422`).

- **Riesgo 4** — Implementar un flujo real de recovery/reset password si el sistema va a operar fuera de un contexto estrictamente administrado.

- **Riesgo 5** — Reescribir los tests de listeners para que verifiquen side effects reales (`Mail::assertSent()`, `Queue::assertPushed()` o equivalente) en vez de solo comprobar que el usuario sigue existiendo en BD.
