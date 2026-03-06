# Backend Docs

## Observabilidad de logs frontend (`warn` / `error`)

### Endpoint

`POST /api/v1/observability/frontend-logs`

### Autenticación

- Requiere `Authorization: Bearer {token}` (Sanctum).
- Multi-tenant por usuario autenticado (`tenant_id` enriquecido en backend).

### Headers requeridos

- `Content-Type: application/json`
- `Accept: application/json`
- `Authorization: Bearer ...`

### Request body

```json
{
  "timestamp": "2026-03-06T20:45:12.431Z",
  "level": "warn",
  "scope": "app",
  "context": ["api", "response-interceptor"],
  "event_name": "api.response.429",
  "meta": {
    "status": 429,
    "url": "/reservations",
    "method": "get"
  },
  "args": ["Demasiadas solicitudes"]
}
```

### Validaciones

- `timestamp`: requerido, fecha válida (ISO8601 recomendado).
- `level`: requerido, enum `warn|error`.
- `scope`: requerido, string, max 100.
- `context`: opcional, array max 10, cada item string max 100.
- `event_name`: opcional, string max 150.
- `meta`: opcional, objeto JSON.
- `args`: opcional, array max 20.
- Regla de consistencia: debe llegar al menos uno entre `event_name` o `args` (con al menos un elemento).
- Tamaño máximo de payload: 32KB.

### Enriquecimiento backend

Cada evento se persiste con:

- `tenant_id`
- `user_id`
- `ip`
- `user_agent`
- `request_id` (si viene en `X-Request-Id` / `X-Request-ID` o atributo de request)
- `occurred_at` (desde `timestamp`)
- `ingested_at` (hora servidor)

### Sanitización y compliance

Se redactan claves sensibles en `meta` y `args` (recursivo) con `[REDACTED]`:

- `authorization`
- `token`
- `password`
- `access_token`
- `refresh_token`
- `cookie`
- `secret`
- `api_key`

### Rate limiting específico

Middleware dedicado para este endpoint:

- 120 requests/minuto por usuario (o IP si no hubiese usuario)
- Burst de 20 requests/10 segundos

Retorna `429` con contrato estándar de error API.

### Respuestas

- `201 Created`
- `422 Unprocessable Entity`
- `401 Unauthorized`
- `429 Too Many Requests`

### Persistencia

Tabla: `frontend_observability_logs`

- PK UUID (`id`)
- Índices por `tenant_id`, `level`, `event_name`, `occurred_at`, `ingested_at`
- Índices compuestos:
  - `(tenant_id, occurred_at)`
  - `(tenant_id, level, occurred_at)`
  - `(event_name, occurred_at)`

### Retención

- Retención inicial: 30 días
- Comando de purga: `observability:purge-frontend-logs --days=30`
- Programación diaria: `02:00`

### Métricas básicas de ingestión por nivel

Se incrementan contadores en caché por nivel:

- `metrics:frontend_observability_logs:{level}`
- `metrics:frontend_observability_logs:{level}:{Ymd}`
