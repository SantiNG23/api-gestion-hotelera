# Auth multi-tenant sin registro libre

Fecha: 2026-03-18

## Estado

- Implementado en backend.
- `POST /api/v1/auth/discover` expuesto.
- `POST /api/v1/auth/login` expuesto.
- `GET /api/v1/auth` ajustado para devolver `tenant`.
- `POST /api/v1/auth` removido del contrato publico.

## Objetivo

Definir el contrato minimo entre frontend y backend para autenticacion multi-tenant sin registro libre.

Esto reemplaza la idea de mezclar login y registro en un unico endpoint publico.

## Decision funcional

- Se elimina el registro libre desde frontend.
- Los tenants y usuarios se crean internamente.
- El frontend solo resuelve acceso de usuarios existentes.
- Si un email pertenece a un solo tenant, la UX debe avanzar directo al ingreso.
- Si un email pertenece a multiples tenants, la UX debe pedir seleccion de tenant antes del login final.

## Contexto tecnico actual

- El sistema ya soporta usuarios con el mismo email en distintos tenants.
- La unicidad actual es compuesta por `tenant_id + email`.
- El backend necesita contexto de tenant antes de autenticar correctamente.
- Por eso el login final no debe depender de adivinar tenant solo por email.

Referencias actuales:

- `database/migrations/2026_03_09_000001_make_users_email_unique_per_tenant.php`
- `app/Services/AuthService.php`
- `app/Tenancy/TenantContextResolver.php`
- `routes/api.php`

## Flujo objetivo

### Paso 1. Descubrir tenants por email

El frontend envia solo el email.

Endpoint propuesto:

- `POST /api/v1/auth/discover`

Request:

```json
{
  "email": "operador@empresa.com"
}
```

### Paso 2. Resolver UX segun respuesta

#### Caso A. El email no existe

Response:

```json
{
  "success": true,
  "data": {
    "mode": "not_found",
    "email": "operador@empresa.com",
    "tenants": []
  },
  "message": "No se encontraron accesos para ese correo."
}
```

Comportamiento esperado en frontend:

- Mostrar mensaje de cuenta no encontrada.
- No ofrecer registro libre.
- Sugerir contacto con administracion si aplica.

#### Caso B. El email existe en un solo tenant

Response:

```json
{
  "success": true,
  "data": {
    "mode": "single_tenant",
    "email": "operador@empresa.com",
    "tenants": [
      {
        "slug": "mirador-centro",
        "name": "Mirador Centro"
      }
    ]
  },
  "message": "Acceso encontrado."
}
```

Comportamiento esperado en frontend:

- Guardar internamente `tenant_slug`.
- No mostrar selector de tenant.
- Avanzar directo a pantalla de password.

#### Caso C. El email existe en multiples tenants

Response:

```json
{
  "success": true,
  "data": {
    "mode": "multi_tenant",
    "email": "operador@empresa.com",
    "tenants": [
      {
        "slug": "mirador-centro",
        "name": "Mirador Centro"
      },
      {
        "slug": "mirador-norte",
        "name": "Mirador Norte"
      }
    ]
  },
  "message": "Selecciona un tenant para continuar."
}
```

Comportamiento esperado en frontend:

- Mostrar selector de tenant.
- Mostrar `name` como etiqueta visible.
- Usar `slug` como identificador interno.
- Luego de elegir tenant, avanzar a pantalla de password.

## Login final

El login final SIEMPRE se hace con tenant explicito.

Endpoint propuesto:

- `POST /api/v1/auth/login`

Request:

```json
{
  "email": "operador@empresa.com",
  "password": "Secret123!",
  "tenant_slug": "mirador-centro"
}
```

Response esperada:

```json
{
  "success": true,
  "data": {
    "token": "plain-text-token",
    "user": {
      "id": 10,
      "name": "Operador Centro",
      "email": "operador@empresa.com"
    },
    "tenant": {
      "id": 3,
      "slug": "mirador-centro",
      "name": "Mirador Centro"
    }
  },
  "message": "Usuario autenticado exitosamente"
}
```

## Bootstrap de sesion

El bootstrap autenticado debe devolver usuario y tenant actual para evitar que frontend tenga que inferir contexto.

Endpoint existente:

- `GET /api/v1/auth`

Response objetivo:

```json
{
  "success": true,
  "data": {
    "id": 10,
    "name": "Operador Centro",
    "email": "operador@empresa.com",
    "tenant": {
      "id": 3,
      "slug": "mirador-centro",
      "name": "Mirador Centro"
    }
  },
  "message": "Usuario obtenido exitosamente"
}
```

## Responsabilidades por capa

### Frontend

- Pedir email primero.
- Llamar a `POST /api/v1/auth/discover`.
- Resolver la UX segun `mode`.
- Enviar siempre `tenant_slug` en el login final, incluso si el email pertenece a un solo tenant.
- Usar `GET /api/v1/auth` para rehidratar sesion y tenant actual.

### Backend

- Exponer `POST /api/v1/auth/discover`.
- Exponer `POST /api/v1/auth/login`.
- Eliminar el comportamiento de registro libre del flujo publico.
- Mantener `tenant_id` prohibido desde cliente.
- Requerir `tenant_slug` en el login final.
- Validar email + password + tenant como combinacion final de acceso.

## Regla importante

La UX puede ocultar la complejidad cuando hay un solo tenant, pero el contrato final de login debe seguir siendo estricto.

En otras palabras:

- `discover` puede ser flexible.
- `login` debe ser explicito.

Esto evita reglas implicitas del tipo:

- a veces `tenant_slug` es obligatorio
- a veces no
- a veces depende de la cantidad de tenants del usuario

Ese tipo de contrato termina siendo una mugre de mantener, testear y debuggear.

## Estados minimos de frontend

- `idle`
- `discovering`
- `tenant_selection`
- `password_entry`
- `authenticating`
- `authenticated`
- `not_found`
- `error`

## Errores funcionales sugeridos

- `not_found`: no se encontraron accesos para ese correo
- `invalid_credentials`: las credenciales proporcionadas son incorrectas
- `tenant_required`: selecciona una cuenta para continuar
- `inactive_tenant`: la cuenta seleccionada no esta disponible

## Alcance de implementacion

Estado actual luego de la implementacion backend:

- `POST /api/v1/auth/discover` implementado
- `POST /api/v1/auth/login` implementado
- `GET /api/v1/auth` devuelve usuario autenticado con `tenant`
- `POST /api/v1/auth` removido del backend y de la documentacion publica

Todavia no implica:

- migracion del frontend al flujo `discover -> login`
- resolucion de tenant por dominio o subdominio

## Siguiente paso recomendado

Implementar en una sesion aparte:

1. adaptacion del frontend al flujo `discover -> login`
2. ajuste de tests contract/smoke de frontend para el nuevo auth
3. endurecer smoke/E2E frontend para fallar ante cualquier uso residual de `POST /api/v1/auth`
