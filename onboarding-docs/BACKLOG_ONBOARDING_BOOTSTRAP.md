# Backlog Ejecutable Backend - Onboarding Bootstrap

## Modo

Draft local. No publica issues en GitHub.

---

## Enfoque de este backlog

Este backlog esta enfocado PRIMARIAMENTE en backend.

Incluye solo trabajo de `api-miradordeluz` para:

- modelar invitaciones bootstrap,
- emitir invitaciones,
- exponer `resolve` y `complete`,
- crear tenant + owner + settings,
- asegurar calidad contractual y regresiones.

No incluye tareas de implementacion frontend como parte principal del backlog.

Si hace falta mencionar trabajo externo, se deja al final como coordinacion o dependencias no-backend, fuera de la jerarquia principal FEATURE -> EPIC -> TASK.

---

## Validacion de Suficiencia de Contexto

- Contexto de negocio explicito: SI
- Objetivo observable: SI
- Alcance y fuera de alcance: SI
- Arquitectura declarada: SI
- Invariantes criticas declaradas: SI
- Stack/tecnologias confirmadas: SI

### Fuentes de verdad utilizadas

- `PLAN_ONBOARDING_BOOTSTRAP_BACKEND.md`
- `CONTRATO_ONBOARDING_BOOTSTRAP_API.md`
- `PLAN_ONBOARDING_BOOTSTRAP_FRONTEND.md` solo como contexto de integracion, no como fuente de backlog principal
- `../app-gestion-hotelera/CONTRATO_CONSUMO_ONBOARDING_BOOTSTRAP_FRONTEND.md` solo para respetar contrato congelado
- `../app-gestion-hotelera/ONBOARDING_BOOTSTRAP_MOCKS.json` solo para validar compatibilidad contractual

### Regla de este backlog

Si una TASK entra en conflicto con el contrato API congelado, gana el contrato. Si una TASK requiere trabajo frontend, ese trabajo no entra como TASK principal aca.

---

## Mapa Jerarquico

- [FEATURE] Implementar onboarding bootstrap backend
  - [EPIC][DOM] Modelar invitaciones bootstrap y ownership inicial
    - [TASK][DOM] Crear migracion y modelo `OnboardingInvitation`
    - [TASK][DOM] Crear factory y helpers de estado de invitacion
    - [TASK][DOM] Agregar `role` al modelo `User`
    - [TASK][DOM] Refactorizar creacion segura de usuario para tenant explicito
  - [EPIC][INFRA] Implementar emision operativa y proteccion del token
    - [TASK][INFRA] Crear config de onboarding y expiracion
    - [TASK][INFRA] Crear `OnboardingTokenService`
    - [TASK][INFRA] Crear mailable y template de invitacion
    - [TASK][INFRA] Crear comando operativo para emitir invitaciones
  - [EPIC][INT] Exponer API publica de onboarding
    - [TASK][INT] Crear requests y resource de onboarding
    - [TASK][INT] Implementar `resolve` respetando contrato congelado
    - [TASK][INT] Implementar `complete` con transaccion completa
    - [TASK][INT] Registrar rutas y rate limit especifico de onboarding
    - [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato
  - [EPIC][INT] Asegurar consistencia operativa y side effects
    - [TASK][INT] Crear inicializacion explicita de settings del owner
    - [TASK][INT] Definir politica de welcome mail y verificacion de email
    - [TASK][INT] Asegurar consumo atomico y no reutilizacion del token
  - [EPIC][INT] Asegurar calidad contractual y regresiones backend
    - [TASK][INT] Cubrir feature tests backend de `resolve`
    - [TASK][INT] Cubrir feature tests backend de `complete`
    - [TASK][INT] Cubrir unit tests backend de token y estados
    - [TASK][INT] Cubrir regresiones de auth existente

---

## [FEATURE] Implementar onboarding bootstrap backend

## Contexto de Negocio
Se necesita un flujo controlado para emitir una invitacion por mail y permitir que el destinatario complete el alta del tenant y del primer usuario administrador sin registro publico abierto ni password generico.

## Objetivo
Implementar en backend un flujo completo de onboarding bootstrap con invitacion single-use, endpoints publicos `resolve` y `complete`, creacion transaccional de tenant + owner + settings, y respuesta final compatible con el auth actual.

## Alcance
- Persistencia de invitaciones bootstrap
- Generacion y proteccion del token
- Emision operativa de invitaciones por comando
- Endpoints publicos `resolve` y `complete`
- Creacion transaccional de tenant, owner y settings iniciales
- Rate limit y errores de dominio compatibles con contrato
- Tests backend del flujo y regresiones principales

## Fuera de Alcance
- Implementacion de la pantalla frontend
- Backoffice web para operadores
- Billing
- Invitaciones de usuarios secundarios
- Multi-db tenancy

## Epics asociadas
- [EPIC][DOM] Modelar invitaciones bootstrap y ownership inicial
- [EPIC][INFRA] Implementar emision operativa y proteccion del token
- [EPIC][INT] Exponer API publica de onboarding
- [EPIC][INT] Asegurar consistencia operativa y side effects
- [EPIC][INT] Asegurar calidad contractual y regresiones backend

## Supuestos explicitos
- El email del invitado no es editable desde frontend
- `complete` devuelve sesion lista usando el shape de auth actual
- La emision inicial de invitaciones se resuelve por comando operativo
- El primer usuario del tenant se persiste con `role = owner`

---

## [EPIC][DOM] Modelar invitaciones bootstrap y ownership inicial

## Dominio / Area Tecnica
DOM

## Objetivo Tecnico
Introducir las piezas de dominio necesarias para representar una invitacion bootstrap y para marcar al primer usuario del tenant como owner.

## Invariantes
- El bootstrap token no se guarda en claro en base de datos
- El owner debe pertenecer a un tenant valido
- El onboarding no confia en `tenant_id` enviado por cliente
- No se crean placeholders que contaminen `discover` o `login`

## Interfaces Esperadas
- Modelo `OnboardingInvitation`
- Tabla `onboarding_invitations`
- Campo `role` en `users`
- Via de creacion de usuario para tenant explicito sin fallback a `Default Tenant`

## Dependencias
- Bloquea por: ninguna
- Desbloquea: [EPIC][INFRA] Implementar emision operativa y proteccion del token; [EPIC][INT] Exponer API publica de onboarding

## Lista de TASK
- [TASK][DOM] Crear migracion y modelo `OnboardingInvitation`
- [TASK][DOM] Crear factory y helpers de estado de invitacion
- [TASK][DOM] Agregar `role` al modelo `User`
- [TASK][DOM] Refactorizar creacion segura de usuario para tenant explicito

### [TASK][DOM] Crear migracion y modelo `OnboardingInvitation`

#### Objetivo
Crear la tabla y el modelo base para almacenar invitaciones bootstrap con expiracion, consumo y revocacion.

#### Archivos o Componentes Afectados
- `database/migrations/*_create_onboarding_invitations_table.php`
- `app/Models/OnboardingInvitation.php`

#### Restricciones
- Guardar `token_hash`, nunca token plano
- Incluir `expires_at`, `consumed_at`, `revoked_at`, `tenant_name_prefill`, `tenant_slug_prefill`, `meta`
- No agregar columnas no definidas en el plan

#### Criterios de Aceptacion
- [ ] Existe tabla `onboarding_invitations` con campos definidos en el plan
- [ ] El modelo expone casts correctos para timestamps y `meta`
- [ ] No se persiste token plano en ninguna columna

#### Tests Esperados
- Unit test del modelo/casts basicos
- Test de persistencia basica

#### Dependencias
- Requiere: ninguna
- Habilita: [TASK][DOM] Crear factory y helpers de estado de invitacion; [TASK][INFRA] Crear `OnboardingTokenService`

### [TASK][DOM] Crear factory y helpers de estado de invitacion

#### Objetivo
Agregar factory y helpers de dominio para derivar estado `pending`, `consumed`, `expired` y `revoked`.

#### Archivos o Componentes Afectados
- `database/factories/OnboardingInvitationFactory.php`
- `app/Models/OnboardingInvitation.php`
- `tests/Unit/Models/OnboardingInvitationTest.php`

#### Restricciones
- El estado se deriva de timestamps, no de enum nuevo
- Los nombres de estado deben respetar el contrato

#### Criterios de Aceptacion
- [ ] La factory permite crear invitaciones pendientes, expiradas, consumidas y revocadas
- [ ] El modelo expone helpers o accessors para estado derivado
- [ ] La logica no introduce estados nuevos

#### Tests Esperados
- Unit tests de derivacion de estado por timestamps

#### Dependencias
- Requiere: [TASK][DOM] Crear migracion y modelo `OnboardingInvitation`
- Habilita: [TASK][INT] Implementar `resolve` respetando contrato congelado; [TASK][INT] Asegurar consumo atomico y no reutilizacion del token

### [TASK][DOM] Agregar `role` al modelo `User`

#### Objetivo
Agregar una representacion explicita del privilegio inicial del primer usuario del tenant.

#### Archivos o Componentes Afectados
- `database/migrations/*_add_role_to_users_table.php`
- `app/Models/User.php`
- `database/factories/UserFactory.php`

#### Restricciones
- Usar `role` segun el plan, no `is_admin` ni `is_owner`
- No romper restricciones actuales de `tenant_id`

#### Criterios de Aceptacion
- [ ] `users` tiene campo `role`
- [ ] `User` permite persistir `role`
- [ ] El valor `owner` puede asignarse durante onboarding

#### Tests Esperados
- Unit tests del modelo `User`

#### Dependencias
- Requiere: ninguna
- Habilita: [TASK][INT] Implementar `complete` con transaccion completa

### [TASK][DOM] Refactorizar creacion segura de usuario para tenant explicito

#### Objetivo
Separar la creacion de usuario para tenant explicito del flujo que hoy puede caer en `Default Tenant`.

#### Archivos o Componentes Afectados
- `app/Services/AuthService.php`
- `app/Services/Onboarding/CompleteOnboardingService.php`
- `tests/Unit/Services/AuthServiceTest.php`

#### Restricciones
- El onboarding no puede reutilizar el fallback a `Default Tenant`
- No romper el auth existente sin cubrir regresion

#### Criterios de Aceptacion
- [ ] Existe una via clara para crear usuario en tenant explicito
- [ ] El flujo onboarding ya no depende del fallback `Default Tenant`
- [ ] La compatibilidad con auth existente queda cubierta por tests

#### Tests Esperados
- Unit tests de servicio o refactor relacionado

#### Dependencias
- Requiere: [TASK][DOM] Agregar `role` al modelo `User`
- Habilita: [TASK][INT] Implementar `complete` con transaccion completa

---

## [EPIC][INFRA] Implementar emision operativa y proteccion del token

## Dominio / Area Tecnica
INFRA

## Objetivo Tecnico
Construir la infraestructura para generar tokens bootstrap, configurar expiracion y enviar emails de invitacion.

## Invariantes
- El token plano solo sale por canal controlado
- La expiracion es configurable
- El link del mail apunta al frontend con query param `token`

## Interfaces Esperadas
- `OnboardingTokenService`
- Config `onboarding.php`
- `OnboardingInvitationMail`
- Comando operativo para emitir invitaciones

## Dependencias
- Bloquea por: [EPIC][DOM] Modelar invitaciones bootstrap y ownership inicial
- Desbloquea: [EPIC][INT] Exponer API publica de onboarding

## Lista de TASK
- [TASK][INFRA] Crear config de onboarding y expiracion
- [TASK][INFRA] Crear `OnboardingTokenService`
- [TASK][INFRA] Crear mailable y template de invitacion
- [TASK][INFRA] Crear comando operativo para emitir invitaciones

### [TASK][INFRA] Crear config de onboarding y expiracion

#### Objetivo
Centralizar expiracion y URL base del frontend onboarding en configuracion.

#### Archivos o Componentes Afectados
- `config/onboarding.php`
- `.env.example`

#### Restricciones
- No hardcodear URL de frontend en servicios o mailables
- Mantener defaults alineados con el plan

#### Criterios de Aceptacion
- [ ] Existe config dedicada para expiracion y URL base
- [ ] `.env.example` documenta variables necesarias

#### Tests Esperados
- Cobertura indirecta via feature tests de emision

#### Dependencias
- Requiere: ninguna
- Habilita: [TASK][INFRA] Crear mailable y template de invitacion; [TASK][INFRA] Crear comando operativo para emitir invitaciones

### [TASK][INFRA] Crear `OnboardingTokenService`

#### Objetivo
Encapsular generacion, hash y comparacion segura de bootstrap tokens.

#### Archivos o Componentes Afectados
- `app/Services/Onboarding/OnboardingTokenService.php`
- `tests/Unit/Services/OnboardingTokenServiceTest.php`

#### Restricciones
- Usar comparacion timing-safe
- No loguear token plano

#### Criterios de Aceptacion
- [ ] El servicio genera token aleatorio de alta entropia
- [ ] El servicio devuelve hash reutilizable para persistencia
- [ ] El servicio permite comparar token plano contra hash persistido

#### Tests Esperados
- Unit tests de generacion y comparacion

#### Dependencias
- Requiere: [TASK][DOM] Crear migracion y modelo `OnboardingInvitation`
- Habilita: [TASK][INFRA] Crear comando operativo para emitir invitaciones; [TASK][INT] Implementar `resolve` respetando contrato congelado

### [TASK][INFRA] Crear mailable y template de invitacion

#### Objetivo
Enviar un email con link al frontend onboarding usando el token plano generado operativamente.

#### Archivos o Componentes Afectados
- `app/Mail/OnboardingInvitationMail.php`
- `resources/views/emails/onboarding-invitation.blade.php`
- `tests/Unit/Mail/OnboardingInvitationMailTest.php`

#### Restricciones
- No incluir password ni datos sensibles innecesarios
- El mail debe comunicar expiracion de la invitacion

#### Criterios de Aceptacion
- [ ] El mailable construye el link con query param `token`
- [ ] El template usa la URL base configurada
- [ ] El contenido no expone informacion sensible extra

#### Tests Esperados
- Unit tests del mailable

#### Dependencias
- Requiere: [TASK][INFRA] Crear config de onboarding y expiracion; [TASK][INFRA] Crear `OnboardingTokenService`
- Habilita: [TASK][INFRA] Crear comando operativo para emitir invitaciones

### [TASK][INFRA] Crear comando operativo para emitir invitaciones

#### Objetivo
Permitir generar una invitacion bootstrap y despachar el mail desde CLI.

#### Archivos o Componentes Afectados
- `app/Console/Commands/*IssueOnboardingInvitation*.php`
- `app/Services/Onboarding/IssueOnboardingInvitationService.php`
- registro de comando correspondiente
- `tests/Feature/Console/IssueOnboardingInvitationCommandTest.php`

#### Restricciones
- No introducir endpoint interno en esta tarea
- Debe persistir invitacion pendiente y despachar mail

#### Criterios de Aceptacion
- [ ] El comando recibe email y prefills opcionales
- [ ] El comando persiste una invitacion pendiente
- [ ] El comando despacha el mailable con el token correcto

#### Tests Esperados
- Feature test de consola con `Mail::fake()`

#### Dependencias
- Requiere: [TASK][INFRA] Crear config de onboarding y expiracion; [TASK][INFRA] Crear `OnboardingTokenService`; [TASK][INFRA] Crear mailable y template de invitacion
- Habilita: [TASK][INT] Implementar `resolve` respetando contrato congelado

---

## [EPIC][INT] Exponer API publica de onboarding

## Dominio / Area Tecnica
INT

## Objetivo Tecnico
Implementar `resolve` y `complete` respetando exactamente `CONTRATO_ONBOARDING_BOOTSTRAP_API.md`.

## Invariantes
- Los errores de dominio deben exponer `errors.code`
- `complete` crea tenant + owner + settings en una sola transaccion logica
- El flujo no acepta `tenant_id` ni `email` del frontend
- La respuesta final es compatible con `AuthResource`

## Interfaces Esperadas
- `POST /api/v1/auth/onboarding/resolve`
- `POST /api/v1/auth/onboarding/complete`
- Requests y resource de onboarding
- Rate limit especifico de onboarding

## Dependencias
- Bloquea por: [EPIC][DOM] Modelar invitaciones bootstrap y ownership inicial; [EPIC][INFRA] Implementar emision operativa y proteccion del token
- Desbloquea: [EPIC][INT] Asegurar calidad contractual y regresiones backend

## Lista de TASK
- [TASK][INT] Crear requests y resource de onboarding
- [TASK][INT] Implementar `resolve` respetando contrato congelado
- [TASK][INT] Implementar `complete` con transaccion completa
- [TASK][INT] Registrar rutas y rate limit especifico de onboarding
- [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato

### [TASK][INT] Crear requests y resource de onboarding

#### Objetivo
Crear las capas HTTP de validacion y serializacion necesarias para onboarding.

#### Archivos o Componentes Afectados
- `app/Http/Requests/ResolveOnboardingInvitationRequest.php`
- `app/Http/Requests/CompleteOnboardingRequest.php`
- `app/Http/Resources/OnboardingInvitationResource.php`

#### Restricciones
- Rechazar `tenant_id`, `email`, `role`, `is_admin`, `is_owner`
- La salida debe alinearse al contrato congelado

#### Criterios de Aceptacion
- [ ] Existen requests especificos para `resolve` y `complete`
- [ ] La validacion rechaza campos prohibidos
- [ ] El resource serializa `status`, `email`, `expires_at`, `tenant_prefill`

#### Tests Esperados
- Feature tests de validacion HTTP

#### Dependencias
- Requiere: [TASK][DOM] Crear migracion y modelo `OnboardingInvitation`
- Habilita: [TASK][INT] Implementar `resolve` respetando contrato congelado; [TASK][INT] Implementar `complete` con transaccion completa

### [TASK][INT] Implementar `resolve` respetando contrato congelado

#### Objetivo
Resolver invitaciones bootstrap y devolver metadata o estados terminales conforme al contrato.

#### Archivos o Componentes Afectados
- `app/Http/Controllers/OnboardingController.php`
- `app/Services/Onboarding/ResolveOnboardingInvitationService.php`
- `routes/api.php`

#### Restricciones
- El estado exitoso permitido es solo `pending`
- Los estados terminales deben mapear a `410` con `errors.code`
- No autenticar ni crear sesion en `resolve`

#### Criterios de Aceptacion
- [ ] `resolve` devuelve `200` con shape exacto en invitacion valida
- [ ] `resolve` devuelve `410` con `errors.code` en `token_invalid`, `token_expired`, `token_consumed`, `token_revoked`
- [ ] `resolve` no filtra token ni datos sensibles extra

#### Tests Esperados
- Feature tests de `resolve`

#### Dependencias
- Requiere: [TASK][DOM] Crear factory y helpers de estado de invitacion; [TASK][INFRA] Crear `OnboardingTokenService`; [TASK][INT] Crear requests y resource de onboarding
- Habilita: [TASK][INT] Registrar rutas y rate limit especifico de onboarding; [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato

### [TASK][INT] Implementar `complete` con transaccion completa

#### Objetivo
Consumir una invitacion valida y crear tenant, owner, settings y token Sanctum en una sola operacion coherente.

#### Archivos o Componentes Afectados
- `app/Http/Controllers/OnboardingController.php`
- `app/Services/Onboarding/CompleteOnboardingService.php`
- `app/Models/Tenant.php`
- `app/Models/User.php`
- `app/Models/UserSetting.php`

#### Restricciones
- No confiar en `email` del payload
- No reutilizar fallback a `Default Tenant`
- Responder con shape compatible a `AuthResource`

#### Criterios de Aceptacion
- [ ] `complete` crea tenant, user owner y settings
- [ ] La invitacion queda consumida de forma definitiva
- [ ] La respuesta devuelve `token`, `user` y `tenant`
- [ ] El rollback deja el sistema consistente ante fallo parcial

#### Tests Esperados
- Feature tests de `complete`
- Tests de rollback

#### Dependencias
- Requiere: [TASK][DOM] Agregar `role` al modelo `User`; [TASK][DOM] Refactorizar creacion segura de usuario para tenant explicito; [TASK][INT] Crear requests y resource de onboarding
- Habilita: [TASK][INT] Crear inicializacion explicita de settings del owner; [TASK][INT] Asegurar consumo atomico y no reutilizacion del token; [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato

### [TASK][INT] Registrar rutas y rate limit especifico de onboarding

#### Objetivo
Publicar `resolve` y `complete` y aislarlos con rate limiting propio del flujo.

#### Archivos o Componentes Afectados
- `routes/api.php`
- `bootstrap/app.php`
- middleware o rate limiter relacionado

#### Restricciones
- No romper el rate limit global existente
- Las rutas deben quedar separadas del login normal dentro del grupo API

#### Criterios de Aceptacion
- [ ] Existen rutas publicas `resolve` y `complete`
- [ ] Ambas aplican rate limit especifico de onboarding
- [ ] Las rutas siguen exigiendo headers JSON de la API

#### Tests Esperados
- Feature tests de rate limit y headers

#### Dependencias
- Requiere: [TASK][INT] Implementar `resolve` respetando contrato congelado; [TASK][INT] Implementar `complete` con transaccion completa
- Habilita: [TASK][INT] Cubrir feature tests backend de `resolve`; [TASK][INT] Cubrir feature tests backend de `complete`

### [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato

#### Objetivo
Garantizar que onboarding exponga `errors.code` de forma consistente y estable.

#### Archivos o Componentes Afectados
- `app/Http/Controllers/OnboardingController.php`
- servicios/excepciones de onboarding
- tests de contrato backend

#### Restricciones
- No introducir codigos distintos a los congelados sin renegociar contrato
- No depender de `message` como unica semantica del error

#### Criterios de Aceptacion
- [ ] Los errores `token_*`, `tenant_slug_taken`, `rate_limited`, `onboarding_unavailable`, `onboarding_conflict` salen con `errors.code`
- [ ] Los status HTTP coinciden con el contrato
- [ ] El texto humano puede variar sin romper consumo frontend

#### Tests Esperados
- Feature/contract tests de respuestas HTTP

#### Dependencias
- Requiere: [TASK][INT] Implementar `resolve` respetando contrato congelado; [TASK][INT] Implementar `complete` con transaccion completa
- Habilita: [TASK][INT] Cubrir feature tests backend de `resolve`; [TASK][INT] Cubrir feature tests backend de `complete`

---

## [EPIC][INT] Asegurar consistencia operativa y side effects

## Dominio / Area Tecnica
INT

## Objetivo Tecnico
Cerrar las decisiones operativas del flujo para que onboarding no dependa de side effects ambiguos o condiciones de carrera.

## Invariantes
- El owner debe salir del onboarding con settings iniciales consistentes
- El token bootstrap no puede reutilizarse
- Las decisiones sobre mail y verificacion no deben quedar implícitas

## Interfaces Esperadas
- Inicializacion de `UserSetting`
- Politica explicita de mail y verificacion de email
- Consumo atomico del token bootstrap

## Dependencias
- Bloquea por: [EPIC][INT] Exponer API publica de onboarding
- Desbloquea: [EPIC][INT] Asegurar calidad contractual y regresiones backend

## Lista de TASK
- [TASK][INT] Crear inicializacion explicita de settings del owner
- [TASK][INT] Definir politica de welcome mail y verificacion de email
- [TASK][INT] Asegurar consumo atomico y no reutilizacion del token

### [TASK][INT] Crear inicializacion explicita de settings del owner

#### Objetivo
Garantizar que el owner creado por onboarding tenga `UserSetting` inicial sin depender de side effects no controlados.

#### Archivos o Componentes Afectados
- `app/Services/Onboarding/CompleteOnboardingService.php`
- `app/Models/UserSetting.php`
- tests relacionados

#### Restricciones
- La inicializacion critica no debe depender exclusivamente de listeners asincronicos
- Debe respetar las reglas tenant de `UserSetting`

#### Criterios de Aceptacion
- [ ] El owner sale del onboarding con settings iniciales creados
- [ ] La creacion respeta `tenant_id` del owner

#### Tests Esperados
- Feature test de `complete`
- Unit test o test de integracion de settings

#### Dependencias
- Requiere: [TASK][INT] Implementar `complete` con transaccion completa
- Habilita: [TASK][INT] Cubrir feature tests backend de `complete`

### [TASK][INT] Definir politica de welcome mail y verificacion de email

#### Objetivo
Volver explicita la decision de onboarding respecto al envio de welcome mail y a `email_verified_at`.

#### Archivos o Componentes Afectados
- `app/Services/Onboarding/CompleteOnboardingService.php`
- listeners/mails relacionados si aplica
- tests relacionados

#### Restricciones
- No dejar el comportamiento librado a accidente del flujo actual
- No introducir un flujo nuevo de verificacion fuera del plan

#### Criterios de Aceptacion
- [ ] Existe una decision implementada y verificable sobre welcome mail
- [ ] Existe una decision implementada y verificable sobre `email_verified_at`
- [ ] La decision queda cubierta por tests

#### Tests Esperados
- Feature o unit tests segun implementacion elegida

#### Dependencias
- Requiere: [TASK][INT] Implementar `complete` con transaccion completa
- Habilita: [TASK][INT] Cubrir feature tests backend de `complete`

### [TASK][INT] Asegurar consumo atomico y no reutilizacion del token

#### Objetivo
Evitar dobles consumos simultaneos o reutilizacion posterior del mismo bootstrap token.

#### Archivos o Componentes Afectados
- `app/Services/Onboarding/CompleteOnboardingService.php`
- `app/Models/OnboardingInvitation.php`
- tests relacionados

#### Restricciones
- El consumo debe ser atomico a nivel de persistencia
- Una request perdedora debe fallar como token ya usado/no valido

#### Criterios de Aceptacion
- [ ] Dos consumos sobre el mismo token no crean dos tenants
- [ ] El segundo intento falla con semantica consistente
- [ ] El estado persistido final es consistente

#### Tests Esperados
- Feature test o test de integracion de no reutilizacion

#### Dependencias
- Requiere: [TASK][INT] Implementar `complete` con transaccion completa; [TASK][DOM] Crear factory y helpers de estado de invitacion
- Habilita: [TASK][INT] Cubrir feature tests backend de `complete`

---

## [EPIC][INT] Asegurar calidad contractual y regresiones backend

## Dominio / Area Tecnica
INT

## Objetivo Tecnico
Blindar contrato, regresiones y comportamiento esperado del flujo onboarding dentro del backend.

## Invariantes
- El contrato congelado no se rompe sin renegociacion explicita
- Cada caso terminal principal tiene test
- El auth existente no se degrada

## Interfaces Esperadas
- Feature tests backend de `resolve`
- Feature tests backend de `complete`
- Unit tests backend de token y estados
- Regresiones de auth existente

## Dependencias
- Bloquea por: [EPIC][INT] Exponer API publica de onboarding; [EPIC][INT] Asegurar consistencia operativa y side effects
- Desbloquea: cierre seguro del feature backend

## Lista de TASK
- [TASK][INT] Cubrir feature tests backend de `resolve`
- [TASK][INT] Cubrir feature tests backend de `complete`
- [TASK][INT] Cubrir unit tests backend de token y estados
- [TASK][INT] Cubrir regresiones de auth existente

### [TASK][INT] Cubrir feature tests backend de `resolve`

#### Objetivo
Verificar que `resolve` respeta contrato, estados terminales, headers y rate limit.

#### Archivos o Componentes Afectados
- `tests/Feature/Auth/OnboardingResolveTest.php`

#### Restricciones
- Aserciones semanticas principales sobre `errors.code`
- Cubrir solo casos definidos en contrato

#### Criterios de Aceptacion
- [ ] Hay tests para success, invalid, expired, consumed y revoked
- [ ] Hay aserciones sobre `errors.code`

#### Tests Esperados
- Feature tests HTTP

#### Dependencias
- Requiere: [TASK][INT] Implementar `resolve` respetando contrato congelado; [TASK][INT] Registrar rutas y rate limit especifico de onboarding; [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato
- Habilita: cierre parcial de calidad backend

### [TASK][INT] Cubrir feature tests backend de `complete`

#### Objetivo
Verificar creacion transaccional, consumo unico y shape final del endpoint `complete`.

#### Archivos o Componentes Afectados
- `tests/Feature/Auth/OnboardingCompleteTest.php`

#### Restricciones
- Debe cubrir rollback y conflictos clave
- Debe validar shape exacto `token/user/tenant`

#### Criterios de Aceptacion
- [ ] Hay tests para success, `tenant_slug_taken`, `token_*`, `onboarding_conflict`
- [ ] Hay test de no reutilizacion del token
- [ ] Hay test de rollback ante fallo critico

#### Tests Esperados
- Feature tests HTTP + DB

#### Dependencias
- Requiere: [TASK][INT] Implementar `complete` con transaccion completa; [TASK][INT] Crear inicializacion explicita de settings del owner; [TASK][INT] Definir politica de welcome mail y verificacion de email; [TASK][INT] Asegurar consumo atomico y no reutilizacion del token; [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato
- Habilita: cierre parcial de calidad backend

### [TASK][INT] Cubrir unit tests backend de token y estados

#### Objetivo
Blindar la logica pura de tokens y estados de invitacion.

#### Archivos o Componentes Afectados
- `tests/Unit/Services/OnboardingTokenServiceTest.php`
- `tests/Unit/Models/OnboardingInvitationTest.php`

#### Restricciones
- Tests unitarios, no duplicar feature tests
- Validar comparacion segura y derivacion de estado

#### Criterios de Aceptacion
- [ ] Hay cobertura de hash/compare
- [ ] Hay cobertura de estados `pending`, `expired`, `consumed`, `revoked`

#### Tests Esperados
- Unit tests

#### Dependencias
- Requiere: [TASK][DOM] Crear factory y helpers de estado de invitacion; [TASK][INFRA] Crear `OnboardingTokenService`
- Habilita: cierre parcial de calidad backend

### [TASK][INT] Cubrir regresiones de auth existente

#### Objetivo
Proteger `discover`, `login` y auth tradicional frente al nuevo flujo onboarding.

#### Archivos o Componentes Afectados
- `tests/Feature/Auth/AuthTest.php`
- nuevos tests de regresion relacionados

#### Restricciones
- No mezclar onboarding con auth tradicional en la implementacion
- Verificar que no aparecen usuarios/tenants placeholder en flujos existentes

#### Criterios de Aceptacion
- [ ] `discover` sigue funcionando como antes
- [ ] `login` sigue exigiendo `tenant_slug`
- [ ] Onboarding no contamina flujos auth existentes

#### Tests Esperados
- Feature tests de regresion auth

#### Dependencias
- Requiere: [TASK][DOM] Refactorizar creacion segura de usuario para tenant explicito; [TASK][INT] Implementar `complete` con transaccion completa
- Habilita: cierre de calidad backend

---

## Dependencias Globales de Ejecucion Backend

1. [TASK][DOM] Crear migracion y modelo `OnboardingInvitation`
2. [TASK][DOM] Crear factory y helpers de estado de invitacion
3. [TASK][DOM] Agregar `role` al modelo `User`
4. [TASK][INFRA] Crear config de onboarding y expiracion
5. [TASK][INFRA] Crear `OnboardingTokenService`
6. [TASK][INFRA] Crear mailable y template de invitacion
7. [TASK][INFRA] Crear comando operativo para emitir invitaciones
8. [TASK][DOM] Refactorizar creacion segura de usuario para tenant explicito
9. [TASK][INT] Crear requests y resource de onboarding
10. [TASK][INT] Implementar `resolve` respetando contrato congelado
11. [TASK][INT] Implementar `complete` con transaccion completa
12. [TASK][INT] Registrar rutas y rate limit especifico de onboarding
13. [TASK][INT] Mapear codigos de dominio y respuestas HTTP del contrato
14. [TASK][INT] Crear inicializacion explicita de settings del owner
15. [TASK][INT] Definir politica de welcome mail y verificacion de email
16. [TASK][INT] Asegurar consumo atomico y no reutilizacion del token
17. [TASK][INT] Cubrir feature tests backend de `resolve`
18. [TASK][INT] Cubrir feature tests backend de `complete`
19. [TASK][INT] Cubrir unit tests backend de token y estados
20. [TASK][INT] Cubrir regresiones de auth existente

---

## Coordinacion Externa / No-Backend

Estas NO forman parte del backlog backend principal, pero conviene tenerlas visibles:

- El frontend debe consumir exactamente `CONTRATO_ONBOARDING_BOOTSTRAP_API.md`.
- El frontend debe usar `../app-gestion-hotelera/ONBOARDING_BOOTSTRAP_MOCKS.json` para avanzar en paralelo.
- La ruta sugerida del frontend es `/onboarding/bootstrap?token=<token>`.
- El frontend no debe enviar `email`, `tenant_id` ni banderas de privilegio.
- Si cambia cualquier `errors.code` o shape de `complete`, hay que renegociar contrato antes de mergear backend.

---

## Checklist de Calidad

### Gate 1 - Suficiencia de contexto

- [x] Hay contexto de negocio explicito
- [x] Hay objetivo observable
- [x] Alcance y fuera de alcance definidos
- [x] Arquitectura declarada por humano
- [x] Invariantes criticas declaradas
- [x] Stack/tecnologias confirmadas

### Gate 2 - FEATURE

- [x] Tiene las 5 secciones obligatorias
- [x] No contiene implementacion tecnica de bajo nivel
- [x] Epics asociadas listadas explicitamente

### Gate 3 - EPIC

- [x] Area tecnica definida (DOM/INFRA/INT)
- [x] Objetivo tecnico concreto
- [x] Invariantes explicitas
- [x] Interfaces esperadas listadas
- [x] Dependencias explicitas
- [x] Lista de TASK completa

### Gate 4 - TASK

- [x] Objetivo atomico y verificable
- [x] Archivos/componentes afectados declarados
- [x] Restricciones claras
- [x] Criterios de aceptacion medibles
- [x] Tests esperados definidos
- [x] Tiempo estimado <= 1 dia

### Gate 5 - Determinismo del backlog

- [x] No hay ambiguedades semanticas criticas
- [x] No se invento arquitectura ni negocio
- [x] Dependencias permiten ejecucion por subagentes
- [x] Cada TASK tiene input y output explicitos
