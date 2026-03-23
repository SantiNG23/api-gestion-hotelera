# Plan Detallado - Onboarding Bootstrap Backend

## 1. Objetivo

Diseñar e implementar en `api-miradordeluz` un flujo de onboarding por invitacion/bootstrap token que permita:

- emitir una invitacion segura a un email especifico,
- exponer endpoints publicos controlados para resolver y consumir esa invitacion,
- permitir que el destinatario complete el alta del tenant y del primer usuario administrador,
- mantener el endurecimiento multitenant actual sin abrir una via fail-open,
- integrarse con el frontend sibling `app-gestion-hotelera` mediante una pantalla publica oculta.

La meta NO es agregar un registro publico generico. La meta es crear un flujo de provisionamiento controlado, single-use, orientado a onboarding inicial.

---

## 2. Contexto actual del backend

### 2.1 Hallazgos verificados en el repo

Hoy el backend ya tiene:

- autenticacion con Sanctum,
- `discover` por email para detectar tenants asociados,
- login publico por `tenant_slug`,
- `TenantContextResolver` para resolver tenant confiable,
- aislamiento tenant-scoped por `tenant_id`,
- `users.tenant_id` obligatorio,
- email unico por tenant,
- listeners y settings iniciales alrededor del usuario,
- middleware API global con validacion de headers, rate limiting y tenant context.

Hoy el backend NO tiene:

- onboarding publico,
- invitaciones,
- bootstrap token,
- roles/permisos/policies,
- nocion de usuario owner/admin inicial,
- endpoint publico para crear tenants,
- endpoint publico para crear usuarios tenant-scoped.

### 2.2 Archivos actuales relevantes

- `routes/api.php`
- `bootstrap/app.php`
- `app/Services/AuthService.php`
- `app/Tenancy/TenantContextResolver.php`
- `app/Tenancy/TenantContext.php`
- `app/Models/User.php`
- `app/Models/Tenant.php`
- `app/Models/UserSetting.php`
- `app/Events/UserRegistered.php`
- `app/Listeners/CreateInitialUserSettings.php`
- `app/Listeners/SendWelcomeEmail.php`
- `app/Http/Requests/AuthDiscoverRequest.php`
- `app/Http/Requests/AuthLoginRequest.php`
- `app/Http/Middleware/ValidateApiHeaders.php`
- `app/Http/Middleware/ApiRateLimiter.php`
- `config/sanctum.php`
- `config/mail.php`
- `tests/Feature/Auth/AuthTest.php`
- `tests/Unit/Services/AuthServiceTest.php`

### 2.3 Restricciones arquitectonicas actuales

Hay dos restricciones que condicionan el diseno:

1. `User` exige `tenant_id` valido al persistirse.
2. `AuthService::createUser()` hoy puede crear un `Default Tenant` si no puede resolver tenant y no existe ninguno.

Ese fallback sirve como bootstrap tecnico en el estado actual, pero para onboarding productivo es un riesgo. No debe quedar habilitado como efecto colateral del nuevo flujo.

---

## 3. Principios de diseno

### 3.1 Principios obligatorios

- El onboarding NO debe mezclarse con el login comun.
- El token bootstrap NO es un bearer token de sesion.
- El email del invitado debe surgir de la invitacion, no del payload libre del cliente.
- El backend no debe confiar en `tenant_id` enviado por el frontend.
- La creacion de tenant + primer admin debe ser transaccional.
- La invitacion debe ser single-use, con expiracion y revocacion.
- El flujo debe ser auditable.
- El onboarding no debe crear placeholders de tenant o user que contaminen `discover` o `login`.

### 3.2 Principios de naming

En este repo, `bootstrap(User $user)` ya existe en `AuthService` con otro significado. Para evitar colision semantica:

- usar el termino `Onboarding` para el modulo publico,
- reservar `bootstrap token` para el concepto de negocio,
- evitar meter logica nueva en `AuthService` con nombres ambiguos.

Recomendacion de naming:

- tabla/modelo: `OnboardingInvitation`
- servicio: `OnboardingService`
- controlador: `OnboardingController`
- requests: `ResolveOnboardingInvitationRequest`, `CompleteOnboardingRequest`

---

## 4. Alcance funcional

### 4.1 In scope

- emision de invitacion bootstrap hacia un email,
- almacenamiento seguro del token,
- resolucion publica de invitacion,
- formulario de completion desde frontend,
- creacion transaccional de tenant,
- creacion del primer usuario administrador,
- provision de settings iniciales,
- consumo definitivo de la invitacion,
- retorno de sesion autenticada opcional al finalizar,
- tests backend completos del flujo.

### 4.2 Out of scope inicial

- billing,
- alta masiva de tenants,
- multiples owners por tenant,
- invitaciones de usuarios secundarios,
- portal completo de operadores internos,
- recuperacion autonoma de invitaciones sin canal operativo,
- multi-db tenancy.

---

## 5. Arquitectura objetivo

### 5.1 Componentes nuevos

#### Dominio de onboarding

- `app/Models/OnboardingInvitation.php`
- `database/migrations/xxxx_xx_xx_xxxxxx_create_onboarding_invitations_table.php`
- `database/factories/OnboardingInvitationFactory.php`

#### Casos de uso / servicios

- `app/Services/Onboarding/IssueOnboardingInvitationService.php`
- `app/Services/Onboarding/ResolveOnboardingInvitationService.php`
- `app/Services/Onboarding/CompleteOnboardingService.php`
- `app/Services/Onboarding/OnboardingTokenService.php`

#### HTTP publico

- `app/Http/Controllers/OnboardingController.php`
- `app/Http/Requests/ResolveOnboardingInvitationRequest.php`
- `app/Http/Requests/CompleteOnboardingRequest.php`
- `app/Http/Resources/OnboardingInvitationResource.php`

#### Mail

- `app/Mail/OnboardingInvitationMail.php`
- `resources/views/emails/onboarding-invitation.blade.php`

#### Operacion interna

Una de estas dos opciones:

- endpoint interno `POST /api/v1/platform/onboarding/invitations`, o
- comando Artisan para emitir invitaciones.

Para la primera etapa, el comando o proceso operativo es mas simple y menos riesgoso.

### 5.2 Integracion con componentes existentes

- `UserRegistered` puede reutilizarse si los listeners sirven para crear settings y enviar welcome.
- si se reutiliza, hay que garantizar que los listeners no agreguen side effects no deseados ni dependan de asincronia peligrosa.
- si no se reutiliza, `CompleteOnboardingService` debe crear settings iniciales explicitamente dentro de la transaccion o inmediatamente despues.

---

## 6. Modelo de datos propuesto

### 6.1 Nueva tabla `onboarding_invitations`

Campos recomendados:

- `id`
- `email` string indexed
- `token_hash` string unique
- `expires_at` timestamp indexed
- `consumed_at` timestamp nullable indexed
- `revoked_at` timestamp nullable indexed
- `tenant_name_prefill` string nullable
- `tenant_slug_prefill` string nullable
- `created_by` foreignId nullable
- `meta` json nullable
- `created_at`
- `updated_at`

### 6.2 Reglas de almacenamiento del token

- generar token aleatorio de alta entropia,
- enviar el token plano solo por email,
- guardar en DB solo el hash,
- comparar con timing-safe compare,
- nunca loguear el token completo.

### 6.3 Cambios en `users`

Hoy `users` no modela permisos. Para el primer usuario administrador hace falta una bandera clara.

Opciones:

#### Opcion A - `is_admin`

- simple,
- barata,
- corta para evolucion futura.

#### Opcion B - `is_owner`

- representa mejor el primer usuario del tenant,
- sigue siendo limitada para crecimiento posterior.

#### Opcion C - `role`

- mas flexible,
- buena base para crecer a `owner`, `admin`, `staff`, etc.

Recomendacion:

- agregar `role` string o enum-backed string,
- valor inicial del onboarding: `owner`.

### 6.4 Cambios en `tenants`

No son obligatorios para el MVP. Eventualmente podria sumarse metadata de onboarding, pero no hace falta desde el dia 1.

---

## 7. Contrato HTTP propuesto

### 7.1 Endpoint publico - resolver invitacion

`POST /api/v1/auth/onboarding/resolve`

#### Request

```json
{
  "token": "<bootstrap-token>"
}
```

#### Response 200

```json
{
  "success": true,
  "message": "Invitacion valida.",
  "data": {
    "status": "pending",
    "email": "owner@cliente.com",
    "expires_at": "2026-03-30T12:00:00Z",
    "tenant_prefill": {
      "name": "Hotel Demo",
      "slug": "hotel-demo"
    }
  }
}
```

#### Errores esperados

- `422` token malformado,
- `410` token expirado,
- `410` token ya consumido,
- `410` token revocado.

### 7.2 Endpoint publico - completar onboarding

`POST /api/v1/auth/onboarding/complete`

#### Request

```json
{
  "token": "<bootstrap-token>",
  "tenant": {
    "name": "Hotel Demo",
    "slug": "hotel-demo"
  },
  "user": {
    "name": "Juan Perez",
    "password": "Secret123!",
    "password_confirmation": "Secret123!"
  }
}
```

#### Response 201 recomendada

Se recomienda devolver el mismo shape base del auth actual para simplificar el frontend:

```json
{
  "success": true,
  "message": "Onboarding completado exitosamente.",
  "data": {
    "token": "<sanctum-token>",
    "user": {
      "id": 1,
      "name": "Juan Perez",
      "email": "owner@cliente.com"
    },
    "tenant": {
      "id": 10,
      "name": "Hotel Demo",
      "slug": "hotel-demo"
    }
  }
}
```

#### Reglas importantes del request

- NO aceptar `tenant_id`,
- NO aceptar `email` editable del usuario,
- NO aceptar `role` arbitrario,
- canonicalizar `tenant.slug` a lowercase,
- aplicar validaciones de password fuertes,
- validar unicidad de slug.

### 7.3 Endpoint o canal interno para emitir invitacion

#### Variante API interna

`POST /api/v1/platform/onboarding/invitations`

Payload sugerido:

```json
{
  "email": "owner@cliente.com",
  "tenant_name_prefill": "Hotel Demo",
  "tenant_slug_prefill": "hotel-demo",
  "expires_in_hours": 72,
  "meta": {
    "source": "backoffice"
  }
}
```

#### Variante operativa inicial

Comando Artisan:

`php artisan onboarding:issue-invitation owner@cliente.com --tenant-name="Hotel Demo" --tenant-slug="hotel-demo"`

Recomendacion de rollout:

- arrancar por comando o proceso operativo,
- sumar endpoint interno cuando exista el backoffice operador.

---

## 8. Flujo de negocio detallado

### 8.1 Emision de invitacion

1. Un operador o proceso interno solicita una invitacion.
2. Se valida que el email tenga formato correcto.
3. Se genera token aleatorio.
4. Se almacena el hash del token.
5. Se persiste la invitacion con `expires_at`.
6. Se envia email con el link al frontend.

### 8.2 Resolucion del link

1. El usuario abre el link recibido.
2. El frontend llama `resolve`.
3. El backend busca la invitacion por hash del token.
4. El backend verifica:
   - token existente,
   - no consumido,
   - no revocado,
   - no expirado.
5. Si es valido, devuelve metadata minima.

### 8.3 Completion del onboarding

1. El usuario envia formulario completo.
2. El backend vuelve a verificar el token.
3. El backend valida slug disponible.
4. El backend inicia una transaccion.
5. Crea tenant con `is_active = true`.
6. Crea usuario owner/admin asociado al tenant.
7. Crea settings iniciales.
8. Marca invitacion como consumida.
9. Genera token Sanctum.
10. Commit.
11. Devuelve usuario + tenant + token.

### 8.4 Consumo unico e idempotencia

El consumo debe ser atomico. Dos requests simultaneas con el mismo token deben terminar asi:

- una gana y consume,
- la otra falla como token ya usado o invalido.

Esto debe protegerse con estrategia de lock o update atomico sobre la fila de invitacion.

---

## 9. Maquina de estados de la invitacion

### 9.1 Estados conceptuales

- `pending`
- `consumed`
- `expired`
- `revoked`

### 9.2 Derivacion por timestamps

No hace falta guardar un enum duro si se calcula por timestamps:

- si `revoked_at != null` -> `revoked`
- si `consumed_at != null` -> `consumed`
- si `expires_at < now()` -> `expired`
- caso contrario -> `pending`

### 9.3 Ventajas de este enfoque

- reduce estado duplicado,
- facilita auditoria,
- evita inconsistencias entre enum y timestamps.

---

## 10. Seguridad

### 10.1 Reglas criticas

- token hasheado en DB,
- token single-use,
- expiracion corta,
- no confiar en email del payload,
- no confiar en tenant_id del payload,
- rate limit mas estricto que el login,
- logs sin exponer token,
- respuestas de error sin filtrar informacion sensible.

### 10.2 Politica de expiracion sugerida

- default: 72 horas,
- configurable por env/config,
- mostrar `expires_at` al frontend para UX clara.

### 10.3 Password policy

Reutilizar la politica fuerte ya usada en el proyecto donde aplique:

- minimo 8,
- mayuscula,
- minuscula,
- numero,
- caracter especial,
- confirmacion.

### 10.4 Validacion del slug

- lowercase obligatorio,
- caracteres validos definidos,
- trim,
- unicidad en tabla `tenants`,
- contemplar que soft delete no libera el slug si el indice unique sigue vigente.

### 10.5 Email verification

Hay que decidir una politica explicita:

#### Opcion A

Marcar `email_verified_at` al completar onboarding porque el usuario accedio desde un link enviado a ese correo.

#### Opcion B

No marcarlo y dejar verificacion para un flujo futuro.

Recomendacion:

- marcarlo durante onboarding si el modelo de negocio asume que la invitacion por correo ya prueba posesion del email.

---

## 11. Integracion con el multitenancy actual

### 11.1 Regla fundamental

El tenant no existe hasta completar onboarding. Entonces el flujo publico NO debe depender del `TenantContextResolver` como el login normal.

### 11.2 Que no hay que hacer

- no extender `resolveForPublicAuth()` para aceptar cualquier payload de onboarding,
- no usar `tenant_id` del cliente,
- no crear tenant placeholder para que luego el usuario lo complete,
- no crear user placeholder para que luego defina password.

### 11.3 Implicancia tecnica

`CompleteOnboardingService` debe ser un caso de uso especial que crea el tenant desde cero y luego crea el usuario owner en ese tenant recien creado.

---

## 12. Integracion con auth actual

### 12.1 Objetivo de compatibilidad

Al finalizar el onboarding, el frontend idealmente deberia poder consumir la respuesta igual que un login normal.

### 12.2 Reutilizacion segura

Se puede reutilizar:

- `AuthResource` como shape base,
- creacion de token Sanctum,
- `UserResource` y `TenantResource` si encajan.

### 12.3 Reutilizacion peligrosa

No reutilizar sin refactor:

- `AuthService::createUser()` mientras mantenga fallback a `Default Tenant`.

### 12.4 Refactor sugerido

Separar metodos:

- `createUserForTenant(Tenant $tenant, array $data)`
- `createUserFromTrustedContext(array $data)`

De esa manera onboarding usa el primero, y auth tradicional conserva el segundo solo donde realmente haga falta.

---

## 13. Integracion con eventos, listeners y settings

### 13.1 Pregunta de diseno

Hay que decidir si el alta del owner debe disparar `UserRegistered`.

### 13.2 Opcion A - reutilizar `UserRegistered`

Pros:

- centraliza creacion de settings y welcome email,
- menos codigo duplicado.

Contras:

- onboarding queda atado a listeners,
- si los listeners son asincronicos y el frontend necesita consistencia inmediata, puede haber carrera.

### 13.3 Opcion B - crear settings explicitamente en onboarding

Pros:

- mas control transaccional,
- menos side effects ocultos,
- onboarding mas deterministico.

Contras:

- algo de logica repetida.

Recomendacion:

- crear settings explicitamente en `CompleteOnboardingService`,
- dejar welcome email como side effect aparte si aporta valor.

---

## 14. Estrategia de implementacion por fases

### Fase 0 - Diseno y acuerdos

- cerrar naming final,
- definir `role = owner` o equivalente,
- definir expiracion por defecto,
- decidir si el completion loguea automaticamente,
- decidir canal inicial de emision de invitacion.

### Fase 1 - Infraestructura base

- migracion `onboarding_invitations`,
- modelo + factory,
- servicio de token hash/compare,
- config dedicada de onboarding,
- mailable + template,
- tests unitarios del token.

### Fase 2 - Emision de invitacion

- comando Artisan o endpoint interno,
- validaciones operativas,
- mail dispatch,
- auditoria minima,
- tests feature del issuance.

### Fase 3 - Endpoints publicos

- `resolve`,
- `complete`,
- form requests,
- resources,
- manejo de errores de dominio,
- rate limit especifico,
- tests feature completos.

### Fase 4 - Refactor de auth relacionado

- separar metodos de creacion de usuario,
- revisar listeners y settings,
- homologar respuesta final con auth actual,
- cubrir regresiones.

### Fase 5 - Hardening

- revocacion manual,
- reenvio de invitacion,
- observabilidad,
- dashboard operativo,
- metricas y alertas.

---

## 15. Plan de testing

### 15.1 Feature tests nuevos

#### Resolucion de invitacion

- token valido,
- token invalido,
- token expirado,
- token ya consumido,
- token revocado.

#### Completion de onboarding

- crea tenant,
- crea owner,
- crea settings,
- consume invitacion,
- devuelve token Sanctum,
- devuelve usuario/tenant correctos,
- no permite reutilizacion,
- rollback por slug duplicado,
- rollback por fallo al crear user,
- rollback por fallo al crear settings.

#### Seguridad

- rechaza `tenant_id`,
- ignora/rechaza `email` arbitrario,
- aplica password policy,
- aplica rate limit,
- no expone token en logs o payloads incorrectos.

#### Regresiones

- `discover` sigue igual,
- `login` sigue igual,
- onboarding parcial no aparece en discover,
- auth existente no queda contaminada por placeholders.

### 15.2 Unit tests nuevos

- `OnboardingTokenService`,
- resolucion de estado por timestamps,
- canonicalizacion de slug,
- creacion del owner con role esperado,
- consumo atomico de invitacion.

---

## 16. Riesgos y mitigaciones

### Riesgo 1 - Reusar `AuthService::createUser()` y disparar `Default Tenant`

Mitigacion:

- refactor previo o uso de servicio dedicado.

### Riesgo 2 - No modelar owner/admin desde el inicio

Mitigacion:

- agregar `role` antes de exponer onboarding.

### Riesgo 3 - Carreras por doble consumo del token

Mitigacion:

- consumo atomico y tests concurrentes.

### Riesgo 4 - Slug ocupado por soft delete previo

Mitigacion:

- validar con mensaje claro y reflejarlo en UX.

### Riesgo 5 - Dependencia oculta de listeners asincronicos

Mitigacion:

- inicializacion critica dentro del servicio principal.

### Riesgo 6 - Expandir indebidamente el public auth resolver

Mitigacion:

- onboarding separado del login actual.

---

## 17. Backlog tecnico sugerido

### Epic A - Infraestructura de invitaciones

- crear tabla y modelo,
- crear token service,
- crear config de expiracion,
- crear factory/test helpers.

### Epic B - Emision operativa

- implementar comando o endpoint interno,
- implementar mailable,
- generar link frontend configurable.

### Epic C - Completion publico

- endpoint resolve,
- endpoint complete,
- request validation,
- transaccion tenant + owner + settings,
- auth response final.

### Epic D - Hardening y observabilidad

- auditoria,
- revocacion,
- metricas,
- manejo operativo de errores.

---

## 18. Decision recomendada

La decision recomendada para este backend es:

- construir un modulo de onboarding separado del auth tradicional,
- introducir una entidad `OnboardingInvitation`,
- crear endpoints publicos `resolve` y `complete`,
- emitir invitaciones inicialmente desde un comando o canal interno controlado,
- crear tenant + owner + settings en una unica transaccion,
- devolver una respuesta compatible con el auth actual para simplificar el frontend,
- evitar por completo cualquier flujo que cree placeholders o que resuelva tenant desde input no confiable.

Este enfoque respeta el endurecimiento multitenant ya presente y deja una base limpia para crecer a invitaciones de usuarios secundarios o backoffice administrativo real mas adelante.
