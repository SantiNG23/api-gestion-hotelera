# Resumen modular de `api-miradordeluz`

## Contexto

- Stack principal detectado: PHP 8.2 + Laravel 12 + Sanctum + PHPUnit 11.
- Arquitectura observada: API REST monolitica versionada en `/api/v1` con modularidad por entidades, controllers y services.
- Enfoque usado: exploracion por subagentes, reporte por modulo y verificacion por tests del modulo y por suite global.

## Estado de trabajo actual

Fecha de actualizacion: 2026-03-10

Tras contrastar los reportes con el codigo actual, los siguientes asuntos ya aparecen resueltos en este branch:

1. Resuelto — unificacion del algoritmo de seleccion de tarifa entre `calculate-price`, `quote` y `applicable-rates` mediante logica comun en `PriceRangeService`.
2. Resuelto — `applicable-rates` ya no expone `price_per_night = 0` de forma engañosa cuando el grupo fue creado via `/price-groups/complete`.
3. Resuelto — validaciones tenant-aware para `cabin_id`, `feature_ids` y requests relacionados.
4. Resuelto — `available_cabins` ya usa `CabinResource::collection(...)`.
5. Resuelto — `FeatureRequest` permite updates parciales reales.
6. Resuelto — `ValidateApiHeaders` acepta variantes validas de media types JSON.
7. Resuelto — reservas bloqueadas canceladas o finalizadas ya no siguen ocupando disponibilidad.
8. Resuelto — `pending_until` se recalcula al convertir bloqueo <-> reserva normal.
9. Resuelto — el comando `reservations:cancel-expired` ya esta programado en el scheduler.
10. Resuelto — al cambiar password se invalidan los tokens activos del usuario.
11. Resuelto — la validacion de capacidad maxima ya es uniforme entre `calculate-price`, `quote`, `store` y `update`.
12. Resuelto — cuando falta configuracion tarifaria en reservas/cotizaciones normales se responde `422`; el precio `0` queda reservado a bloqueos.
13. Resuelto — existe `UserResource` explicito y ya no existe la ruta legacy `/api/user` fuera de `/api/v1`.

Puntos aun abiertos o que requieren decision funcional:

1. Pendiente — definir la politica de reutilizacion de `dni` cuando un cliente fue soft-deleted.
2. Pendiente — decidir si la reserva puede seguir sobreescribiendo datos del cliente por coincidencia de `dni`.
3. Pendiente — preservar mejor trazabilidad historica en reservas mediante relaciones `client`/`cabin` compatibles con soft delete.
4. Pendiente — implementar o remover listeners placeholder de onboarding y confirmacion de reservas.

## Estado general

- La base funcional actual esta bien cubierta por tests y la suite completa pasa en verde: `283` tests, `1055` assertions.
- Los modulos con mayor madurez funcional son `reservas_y_operacion` y `tarifas_y_precios`, pero tambien concentran los riesgos mas importantes.
- El sistema implementa multitenancy en gran parte del dominio mediante `BelongsToTenant`, aunque hay huecos de validacion cross-tenant en algunos flujos.
- Hay varias reglas de negocio ya codificadas que conviene formalizar porque hoy existen mas por implementacion que por documentacion.

## Resumen por modulo

### 1. `clientes`

- Funcionalidad actual: CRUD, filtros, busqueda por DNI e integracion directa con reservas.
- Reglas centrales: `dni` unico por tenant, soft delete, resolucion de cliente por DNI durante creacion/edicion de reservas.
- Testing ejecutado por modulo: `32` tests OK, `111` assertions.
- Hallazgos clave:
  - crear/editar reservas puede sobreescribir datos del cliente si coincide el DNI;
  - un cliente borrado logicamente sigue reteniendo su DNI por la restriccion unica;
  - faltan tests de tenant isolation, validaciones finas y filtros no cubiertos.
- Lectura general: modulo estable, pero con riesgos de trazabilidad e integridad historica.

### 2. `cabanas_y_caracteristicas`

- Funcionalidad actual: CRUD de cabanas y features, relacion many-to-many, soporte directo a disponibilidad y pricing.
- Reglas centrales: `capacity` entre 1 y 50, sincronizacion de `feature_ids`, disponibilidad solo sobre cabanas activas.
- Testing ejecutado por modulo: `82` tests OK, `339` assertions.
- Hallazgos clave:
  - las validaciones tenant-aware principales ya quedaron alineadas con el codigo actual;
  - sigue habiendo riesgo de trazabilidad si una reserva referencia cliente/cabaña soft-deleted y luego se consume desde disponibilidad;
  - faltan tests de relaciones cross-tenant historicas y del endpoint `GET /features/{id}`.
- Lectura general: modulo robusto en CRUD y operaciones basicas; la deuda principal ya no esta en validaciones tenant-aware sino en trazabilidad historica y cobertura complementaria.

### 3. `tarifas_y_precios`

- Funcionalidad actual: CRUD de grupos/rangos, precios por cabana y cantidad de huespedes, calculo de precio y cotizacion.
- Reglas centrales: un solo grupo default por tenant, calculo noche por noche, senia del 50 por ciento y rechazo `422` en flujos normales cuando falta configuracion tarifaria.
- Testing ejecutado por modulo: `64` tests OK, `299` assertions.
- Hallazgos clave:
  - la divergencia principal entre `applicable-rates` y `calculate-price` ya fue corregida;
  - la falta de configuracion tarifaria en cotizaciones/reservas normales ya no degrada a `0` silencioso y ahora responde `422`;
  - siguen abiertos los casos de rangos superpuestos creados por CRUD simple y algunas inconsistencias menores de contrato, como mensajes de validacion y semantica del rango inclusive en `applicable-rates`.
- Lectura general: sigue siendo un modulo sensible, pero el riesgo principal vuelve a estar en solapamientos, semantica entre endpoints y claridad operativa, no en el fallback silencioso a `0`.

### 4. `reservas_y_operacion`

- Funcionalidad actual: CRUD de reservas, confirmacion, pago de saldo, check-in, check-out, cancelacion, bloqueos manuales, disponibilidad, resumen diario y auto cancelacion de pendientes vencidas.
- Reglas centrales: ciclo de vida por estados, bloqueos con cliente tecnico, pagos `deposit` y `balance`, disponibilidad semiabierta y resumen diario operativo.
- Testing ejecutado por modulo: `177` tests OK, `562` assertions.
- Hallazgos clave:
  - existe tension conceptual entre `cancel` y `delete`;
  - `calculate-price`, `quote`, `store` y `update` ya comparten validacion uniforme de capacidad maxima;
  - una reserva/cotizacion normal sin tarifa configurada ahora responde `422`, mientras que el precio `0` queda reservado a bloqueos;
  - la trazabilidad historica pierde contexto cuando cliente o cabaña asociados fueron soft-deleted.
- Lectura general: es el corazon del negocio y el modulo mas solido en cobertura; los riesgos vigentes ya no pasan por `is_blocked` o `pending_until`, sino por consistencia de reglas y trazabilidad historica.

### 5. `acceso_y_perfil_de_usuario`

- Funcionalidad actual: login/registro combinado en `POST /auth`, logout total, consulta/edicion de perfil y cambio de password autenticado.
- Reglas centrales: password fuerte, headers JSON estrictos, rate limit general, logout que revoca todos los tokens.
- Testing ejecutado por modulo: `35` tests OK, `125` assertions.
- Hallazgos clave:
  - el endpoint de auth sigue mezclando login y registro en el mismo contrato;
  - ya existe `UserResource`, por lo que auth/perfil usan un contrato explicito y estable para serializar usuario;
  - la ruta legacy `/api/user` fue eliminada y el acceso al usuario autenticado queda normalizado bajo `/api/v1`;
  - no hay flujo real de recovery/reset password y los listeners de onboarding siguen siendo placeholders.
- Lectura general: modulo funcional y mas alineado con multitenancy y contrato API que en el reporte original, pero con deuda de side effects reales, recovery/reset y diseño del endpoint dual de auth.

## Reglas de negocio transversales detectadas

- Multitenancy por scope global en modelos principales; no todos los `exists` o validadores reflejan ese mismo criterio.
- Sanitizacion previa de strings en requests API: trim, remocion de caracteres de control y normalizacion de espacios.
- API protegida por `auth:sanctum` en casi todos los modulos operativos.
- Uso fuerte de soft delete en entidades del dominio.
- Respuestas API con formato relativamente uniforme.

## Riesgos prioritarios

### Riesgo alto

- Ambiguedad operativa en clientes por reutilizacion de DNI con soft delete.
- Perdida de trazabilidad historica cuando cliente/cabaña relacionados fueron soft-deleted.

### Riesgo medio

- Actualizacion automatica de datos del cliente por coincidencia de DNI desde reservas.
- Listeners registrados pero sin efecto funcional real.
- Semantica operativa mezclada en `expiring_pending` y deuda de definicion en el endpoint dual `POST /auth`.

### Riesgo bajo

- Diferencias menores de mensajes de validacion o contratos entre endpoints parecidos.

## Backlog priorizado

El siguiente backlog refleja que los tres P0 originales ya quedaron resueltos en este branch y separa lo que conviene abordar ahora, lo que requiere definicion funcional previa y lo que puede quedar para una segunda tanda sin comprometer la operacion principal.

### P0 — resuelto en este branch

| Prioridad | Tema | Modulos | Tipo | Por que entra primero |
| --- | --- | --- | --- | --- |
| P0 | Unificar validacion de capacidad maxima entre `calculate-price`, `quote`, `store` y `update` | `reservas_y_operacion`, `cabanas_y_caracteristicas` | Bug funcional | Ya resuelto: la misma regla de negocio ahora se aplica de forma uniforme en todos los flujos reservables. |
| P0 | Señalizar explicitamente cuando una reserva/cotizacion cae a precio `0` por falta de configuracion | `tarifas_y_precios`, `reservas_y_operacion` | Bug funcional | Ya resuelto: las reservas/cotizaciones normales responden `422` cuando falta tarifa; el precio `0` queda reservado a bloqueos. |
| P0 | Crear `UserResource` explicito y normalizar o eliminar la ruta raw `/api/user` | `acceso_y_perfil_de_usuario` | Contrato API | Ya resuelto: existe `UserResource` y se elimino la ruta legacy fuera de `/api/v1`. |

### P1 — siguiente foco recomendado

| Prioridad | Tema | Modulos | Tipo | Por que sigue |
| --- | --- | --- | --- | --- |
| P1 | Preservar trazabilidad historica de reservas cuando cliente/cabaña fueron soft-deleted | `reservas_y_operacion`, `clientes`, `cabanas_y_caracteristicas` | Integridad historica | La operacion diaria y los historiales pueden perder contexto o romperse cuando relaciones blandamente eliminadas pasan a `null`. |
| P1 | Implementar o desactivar listeners placeholder de onboarding y confirmacion de reservas | `acceso_y_perfil_de_usuario`, `reservas_y_operacion` | Consistencia tecnica | Hoy existen listeners registrados cuyo nombre promete side effects reales, pero su implementacion actual es vacia o solo loguea. |
| P1 | Corregir cobertura faltante de endpoints y escenarios historicos | transversal | Calidad / testing | Aun faltan pruebas directas para `cancel`, `GET /features/{id}` y escenarios con entidades soft-deleted o decisiones funcionales pendientes sobre clientes. |

### P2 — requiere decision funcional antes de tocar codigo

| Prioridad | Tema | Modulos | Tipo | Decision necesaria |
| --- | --- | --- | --- | --- |
| P2 | Politica de reutilizacion de `dni` cuando un cliente fue soft-deleted | `clientes` | Regla de negocio | Hay que decidir si el DNI se puede reutilizar, si debe restaurarse el cliente eliminado o si debe bloquearse explicitamente con mensaje claro. |
| P2 | Politica de sobreescritura automatica de datos del cliente por coincidencia de `dni` desde reservas | `clientes`, `reservas_y_operacion` | Regla de negocio | Hoy el sistema actualiza al cliente existente de forma silenciosa; antes de cambiarlo hay que definir si eso es deseado, parcial o prohibido. |
| P2 | Semantica de `expiring_pending` en resumen diario | `reservas_y_operacion` | Regla operativa | Conviene decidir si se mantienen juntas las alertas de vencimiento de seña y saldo pendiente o si deben separarse por tipo de accion operativa. |
| P2 | Separar login y registro en endpoints distintos o sostener el endpoint dual `POST /auth` | `acceso_y_perfil_de_usuario` | Diseño API | No es un bug urgente, pero si una definicion de contrato que afecta documentacion, onboarding y evolucion futura del modulo. |

### P3 — mejoras secundarias / prolijidad

| Prioridad | Tema | Modulos | Tipo | Motivo |
| --- | --- | --- | --- | --- |
| P3 | Corregir mensajes de validacion inconsistentes menores | `tarifas_y_precios` y otros | UX técnica | No rompen la logica principal, pero generan friccion e interpretaciones erradas en clientes API. |
| P3 | Documentar explicitamente `cancel` vs `delete` en reservas y alinear nombres de tests | `reservas_y_operacion` | Documentacion / testing | El dominio los distingue bien, pero parte de la documentacion historica y los tests siguen mezclando ambos conceptos. |
| P3 | Revisar semantica de rango inclusive en `applicable-rates` vs rango de estadia real | `tarifas_y_precios` | Claridad funcional | No necesariamente es un bug, pero conviene evitar que frontend e integraciones interpreten ambos endpoints como equivalentes literales. |

### Orden recomendado de ejecucion

1. Trazabilidad historica con relaciones soft-deleted.
2. Limpieza o implementacion real de listeners placeholder.
3. Definiciones funcionales sobre `dni`, sobreescritura de clientes y contrato de auth.

## Documentos originales

- `report-clientes.md`
- `report-cabanas-y-caracteristicas.md`
- `report-tarifas-y-precios.md`
- `report-reservas-y-operacion.md`
- `report-acceso-y-perfil-de-usuario.md`
