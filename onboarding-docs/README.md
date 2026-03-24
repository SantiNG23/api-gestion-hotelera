# Onboarding Bootstrap - Setup Operativo

Este README explica **qué tenés que configurar en `api-miradordeluz` para que el flujo de onboarding bootstrap empiece a funcionar**.

No es teoría. Es setup real del backend implementado.

---

## 1. Qué hace este módulo

El flujo onboarding bootstrap permite:

- emitir una invitación por mail,
- abrir una pantalla pública de frontend con `?token=...`,
- resolver la invitación con un endpoint público,
- completar el alta de `tenant + owner + settings`,
- devolver sesión autenticada lista para usar.

---

## 2. Requisitos mínimos

Antes de probar onboarding, asegurate de tener funcionando lo básico del proyecto:

- base de datos configurada,
- `APP_KEY` generada,
- migraciones corridas,
- mail configurado,
- frontend con una ruta pública de onboarding.

Si eso no está, dejate de joder: onboarding no va a levantar mágicamente.

---

## 3. Variables de entorno que importan

En `.env` necesitás como mínimo esto:

```env
APP_URL=http://localhost

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

ONBOARDING_FRONTEND_URL="http://localhost:3000/onboarding/bootstrap"
ONBOARDING_INVITATION_EXPIRES_IN_HOURS=72
```

### Qué significa cada una

- `ONBOARDING_FRONTEND_URL`
  - URL base adonde apunta el mail de invitación.
  - El backend le agrega `?token=<bootstrap-token>`.
  - Ejemplo esperado: `http://localhost:3000/onboarding/bootstrap`

- `ONBOARDING_INVITATION_EXPIRES_IN_HOURS`
  - Horas de validez de la invitación.
  - Default actual: `72`.

- `MAIL_*`
  - Necesario para que el comando de emisión pueda mandar la invitación.
  - En local podés usar `MAIL_MAILER=log` o Mailpit/Mailhog.

---

## 4. Configuración interna relevante

Archivo: `config/onboarding.php`

Hoy el backend tiene esta política por defecto:

- `mark_email_as_verified = true`
- `send_welcome_mail = false`

Eso significa:

- al completar onboarding, el owner queda con `email_verified_at` seteado,
- no se manda welcome mail extra por defecto.

Si querés cambiar esa política, hoy se hace en `config/onboarding.php`. **No está expuesta por env**.

---

## 5. Migraciones necesarias

Tenés que correr migraciones para que existan:

- tabla `onboarding_invitations`,
- columna `users.role`.

Comando:

```bash
php artisan migrate
```

---

## 6. Frontend que tiene que existir

El backend manda el usuario a la URL configurada en `ONBOARDING_FRONTEND_URL`.

El frontend debe tener una pantalla pública que:

1. lea `token` desde query string,
2. llame a `POST /api/v1/auth/onboarding/resolve`,
3. renderice el formulario,
4. llame a `POST /api/v1/auth/onboarding/complete`,
5. persista la sesión al recibir `token + user + tenant`.

Ruta sugerida actual:

```text
/onboarding/bootstrap?token=<token>
```

---

## 7. Endpoints disponibles

### Resolver invitación

```http
POST /api/v1/auth/onboarding/resolve
```

Body:

```json
{
  "token": "btp_live_..."
}
```

### Completar onboarding

```http
POST /api/v1/auth/onboarding/complete
```

Body:

```json
{
  "token": "btp_live_...",
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

### Headers requeridos

Ambos endpoints pasan por middleware API, así que mandá:

```http
Accept: application/json
Content-Type: application/json
```

---

## 8. Cómo emitir una invitación

Canal operativo actual: **Artisan command**.

```bash
php artisan onboarding:issue-invitation owner@cliente.com --tenant-name="Hotel Demo" --tenant-slug="hotel-demo"
```

Opciones disponibles:

- argumento obligatorio: `email`
- opcional: `--tenant-name`
- opcional: `--tenant-slug`

Qué hace este comando:

- genera token seguro,
- guarda solo el hash en DB,
- crea una invitación `pending`,
- manda el mail al destinatario.

---

## 9. Qué validar después del setup

Checklist mínimo:

- [ ] `php artisan migrate` corrió sin errores
- [ ] existe configuración válida de `MAIL_*`
- [ ] `ONBOARDING_FRONTEND_URL` apunta a una pantalla real del frontend
- [ ] el comando `onboarding:issue-invitation` manda mail o lo deja en logs
- [ ] el link del mail llega con `?token=`
- [ ] `resolve` responde `200` para una invitación válida
- [ ] `complete` crea `tenant`, `user owner`, `user_settings` y devuelve sesión

---

## 10. Limitaciones operativas actuales

- No existe backoffice web para emitir invitaciones.
- La emisión inicial es solo por comando.
- No existe reenvío ni revocación manual expuestos por UI.
- La política de completion (`email_verified_at` / welcome mail) vive en config, no en env.

---

## 11. Archivos clave para tocar si algo falla

- `config/onboarding.php`
- `.env`
- `app/Console/Commands/IssueOnboardingInvitationCommand.php`
- `app/Services/Onboarding/IssueOnboardingInvitationService.php`
- `app/Services/Onboarding/ResolveOnboardingInvitationService.php`
- `app/Services/Onboarding/CompleteOnboardingService.php`
- `app/Mail/OnboardingInvitationMail.php`
- `routes/api.php`

---

## 12. Documentos relacionados

- `onboarding-docs/PLAN_ONBOARDING_BOOTSTRAP_BACKEND.md`
- `onboarding-docs/CONTRATO_ONBOARDING_BOOTSTRAP_API.md`
- `onboarding-docs/BACKLOG_ONBOARDING_BOOTSTRAP.md`

Si querés entender el flujo funcional, leé el plan.
Si querés integrar frontend sin romper contrato, leé el contrato.
Si querés ver alcance y orden de implementación, leé el backlog.
