# Contrato API - Onboarding Bootstrap

## 1. Objetivo del contrato

Este documento congela el contrato HTTP entre frontend y backend para el flujo de onboarding por bootstrap token.

El objetivo es permitir trabajo en paralelo entre:

- el equipo/backend de `api-miradordeluz`,
- el equipo/frontend de `app-gestion-hotelera`.

Este contrato define:

- endpoints publicos,
- payloads de request,
- payloads de response,
- codigos HTTP,
- codigos de dominio,
- validaciones observables por el frontend,
- comportamiento post-exito.

Lo que NO define este contrato:

- implementacion interna del backend,
- naming exacto de servicios/clases internas,
- estrategia interna de eventos/listeners,
- canal inicial de emision de invitaciones.

---

## 2. Decisiones congeladas

Estas decisiones se consideran cerradas para destrabar implementacion paralela:

1. El onboarding va por flujo publico separado del login comun.
2. El frontend consume dos endpoints publicos:
   - `POST /api/v1/auth/onboarding/resolve`
   - `POST /api/v1/auth/onboarding/complete`
3. El bootstrap token:
   - llega por mail,
   - es single-use,
   - expira,
   - no es token de sesion.
4. El email del invitado no es editable desde frontend.
5. El frontend puede editar `tenant.name` y `tenant.slug` aunque exista prefill.
6. `complete` devuelve sesion autenticada lista para usar.
7. La respuesta de `complete` respeta el shape de auth actual:
   - `token`
   - `user`
   - `tenant`
8. Los errores de dominio se exponen mediante `errors.code` para que frontend pueda mapear estados y mensajes.

---

## 3. Envelope estandar

### 3.1 Respuesta exitosa

```json
{
  "success": true,
  "message": "Texto legible para usuario/operacion",
  "data": {}
}
```

### 3.2 Respuesta con error

```json
{
  "success": false,
  "message": "Texto legible para usuario/operacion",
  "errors": {
    "code": ["domain_code"],
    "field_name": ["Mensaje de validacion"]
  }
}
```

### 3.3 Regla de compatibilidad

Para el flujo onboarding, aun cuando el status no sea `422`, el backend debe incluir `errors.code` cuando exista un error de dominio conocido. Esto es importante para desacoplar la UX del frontend del texto humano del `message`.

---

## 4. Endpoint - Resolve Invitation

### 4.1 Request

- Metodo: `POST`
- URL: `/api/v1/auth/onboarding/resolve`
- Auth: publica
- Headers requeridos:
  - `Accept: application/json`
  - `Content-Type: application/json`

#### Body

```json
{
  "token": "btp_live_XXXXXXXXXXXXXXXX"
}
```

### 4.2 Validaciones del request

- `token` es requerido.
- `token` debe ser string.
- `token` no puede venir vacio.

### 4.3 Response exitosa - 200

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

### 4.4 Semantica de campos

- `status`
  - por ahora el unico valor exitoso permitido es `pending`
- `email`
  - email de la invitacion
  - se muestra en frontend en modo read-only
- `expires_at`
  - ISO 8601 UTC
- `tenant_prefill`
  - puede venir con valores `null` en sus propiedades
  - puede incluso venir completo como `null` si no hay prefill

### 4.5 Errores esperados

#### 422 - token malformado o request invalido

```json
{
  "success": false,
  "message": "Error de validacion",
  "errors": {
    "code": ["invalid_request"],
    "token": ["El token es obligatorio."]
  }
}
```

#### 410 - token inexistente o invalido

```json
{
  "success": false,
  "message": "La invitacion no es valida.",
  "errors": {
    "code": ["token_invalid"]
  }
}
```

#### 410 - token expirado

```json
{
  "success": false,
  "message": "La invitacion ha expirado.",
  "errors": {
    "code": ["token_expired"]
  }
}
```

#### 410 - token ya consumido

```json
{
  "success": false,
  "message": "La invitacion ya fue utilizada.",
  "errors": {
    "code": ["token_consumed"]
  }
}
```

#### 410 - token revocado

```json
{
  "success": false,
  "message": "La invitacion fue revocada.",
  "errors": {
    "code": ["token_revoked"]
  }
}
```

#### 429 - rate limit

```json
{
  "success": false,
  "message": "Demasiadas solicitudes",
  "errors": {
    "code": ["rate_limited"]
  }
}
```

---

## 5. Endpoint - Complete Onboarding

### 5.1 Request

- Metodo: `POST`
- URL: `/api/v1/auth/onboarding/complete`
- Auth: publica
- Headers requeridos:
  - `Accept: application/json`
  - `Content-Type: application/json`

#### Body

```json
{
  "token": "btp_live_XXXXXXXXXXXXXXXX",
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

### 5.2 Reglas de payload

- `token` requerido.
- `tenant.name` requerido.
- `tenant.slug` requerido.
- `user.name` requerido.
- `user.password` requerido.
- `user.password_confirmation` requerida.
- `user.password` y `user.password_confirmation` deben coincidir.
- `tenant_id` esta prohibido en cualquier nivel.
- `email` del user no debe enviarse; el backend lo toma de la invitacion.
- `role`, `is_admin`, `is_owner` o equivalentes no deben enviarse desde frontend.

### 5.3 Validaciones visibles para frontend

#### Tenant

- `tenant.name`
  - requerido
  - maximo recomendado: 255

- `tenant.slug`
  - requerido
  - lowercase canonicalizado
  - maximo recomendado: 255
  - formato recomendado: `^[a-z0-9]+(?:-[a-z0-9]+)*$`
  - unicidad real validada por backend

#### User

- `user.name`
  - requerido
  - maximo recomendado: 255

- `user.password`
  - minimo 8
  - requiere mayuscula
  - requiere minuscula
  - requiere numero
  - requiere caracter especial

### 5.4 Response exitosa - 201

```json
{
  "success": true,
  "message": "Onboarding completado exitosamente.",
  "data": {
    "token": "1|sanctum_plain_text_token",
    "user": {
      "id": 101,
      "name": "Juan Perez",
      "email": "owner@cliente.com",
      "created_at": "2026-03-23T19:21:11.000000Z",
      "updated_at": "2026-03-23T19:21:11.000000Z"
    },
    "tenant": {
      "id": 55,
      "slug": "hotel-demo",
      "name": "Hotel Demo"
    }
  }
}
```

### 5.5 Regla de compatibilidad con auth actual

La estructura de `data` debe ser compatible con `AuthResource` actual:

- `data.token`
- `data.user`
- `data.tenant`

El frontend puede asumir que, ante exito, ya dispone de sesion lista.

### 5.6 Errores esperados

#### 422 - errores de validacion de payload

```json
{
  "success": false,
  "message": "Error de validacion",
  "errors": {
    "code": ["invalid_request"],
    "tenant.slug": ["El slug del tenant es obligatorio."],
    "user.password": ["La contrasena debe contener al menos una mayuscula, una minuscula, un numero y un caracter especial."]
  }
}
```

#### 410 - token invalido

```json
{
  "success": false,
  "message": "La invitacion no es valida.",
  "errors": {
    "code": ["token_invalid"]
  }
}
```

#### 410 - token expirado

```json
{
  "success": false,
  "message": "La invitacion ha expirado.",
  "errors": {
    "code": ["token_expired"]
  }
}
```

#### 410 - token ya consumido

```json
{
  "success": false,
  "message": "La invitacion ya fue utilizada.",
  "errors": {
    "code": ["token_consumed"]
  }
}
```

#### 410 - token revocado

```json
{
  "success": false,
  "message": "La invitacion fue revocada.",
  "errors": {
    "code": ["token_revoked"]
  }
}
```

#### 409 - slug ya tomado

```json
{
  "success": false,
  "message": "El slug seleccionado no esta disponible.",
  "errors": {
    "code": ["tenant_slug_taken"],
    "tenant.slug": ["El slug seleccionado no esta disponible."]
  }
}
```

#### 409 - email ya asociado a conflicto de onboarding

Solo si el backend detecta una condicion operativa no recuperable con ese email/token.

```json
{
  "success": false,
  "message": "No se pudo completar el onboarding para este correo.",
  "errors": {
    "code": ["onboarding_conflict"]
  }
}
```

#### 429 - rate limit

```json
{
  "success": false,
  "message": "Demasiadas solicitudes",
  "errors": {
    "code": ["rate_limited"]
  }
}
```

#### 503 - servicio temporalmente no disponible

```json
{
  "success": false,
  "message": "El onboarding no esta disponible temporalmente.",
  "errors": {
    "code": ["onboarding_unavailable"]
  }
}
```

---

## 6. Tabla oficial de codigos de dominio

| Code | Endpoint | HTTP | Significado | Accion esperada en frontend |
| --- | --- | --- | --- | --- |
| `invalid_request` | ambos | 422 | payload invalido | mostrar errores de formulario o request |
| `token_invalid` | ambos | 410 | token inexistente/malformado/no reconocible | pantalla terminal invalida |
| `token_expired` | ambos | 410 | token vencido | pantalla terminal expirada |
| `token_consumed` | ambos | 410 | token ya utilizado | pantalla terminal usada |
| `token_revoked` | ambos | 410 | token revocado operativamente | pantalla terminal revocada |
| `tenant_slug_taken` | complete | 409 | slug ya ocupado | error inline en `tenant.slug` |
| `onboarding_conflict` | complete | 409 | conflicto operativo no recuperable | pantalla/error general |
| `rate_limited` | ambos | 429 | exceso de requests | mensaje transitorio / retry later |
| `onboarding_unavailable` | complete | 503 | backend no disponible temporalmente | mensaje general con retry |

---

## 7. Reglas de consumo frontend

1. `resolve` se ejecuta al abrir la pagina con token presente.
2. Si `resolve` da `200`, se renderiza formulario.
3. Si `resolve` devuelve `token_*`, se renderiza estado terminal segun `errors.code`.
4. `complete` usa el mismo token que `resolve`.
5. Si `complete` da `201`, frontend persiste sesion y redirige al area autenticada.
6. El frontend no debe inferir estado de error por texto del `message`; debe usar `errors.code`.
7. El frontend no debe persistir el bootstrap token en storage de auth.

---

## 8. Regla de versionado del contrato

Mientras no cambien:

- endpoints,
- envelope,
- campos requeridos,
- codigos de dominio,
- shape de `complete` exitoso,

el frontend puede avanzar con mocks y luego conectar el backend real sin romperse.

Si alguna de estas piezas cambia, hay que versionar o renegociar contrato. Nada de cambiar payloads por abajo y hacerse el boludo.
