# Flujos E2E frontend-backend

Inventario de flujos end-to-end que hay que cubrir para validar el sistema completo, desde la accion del usuario en frontend hasta la respuesta real del backend.

Fecha: 2026-03-12

## Criterio

- Esto NO es una lista de endpoints sueltos. Son flujos de negocio.
- Cada flujo debe correr contra backend real, sin mocks de API en frontend.
- La prioridad `smoke` cubre lo que tiene que quedar vivo despues de cada cambio serio.
- La prioridad `secundario` cubre regresion ampliada, bordes de contrato y consistencia operativa.

## Fixture demo smoke

- Seeder: `php artisan db:seed --class=DemoDataSeeder`
- Password comun demo: `Demo123!`
- Tenant A: `smoke-sierra-clara` -> usuario `smoke.sierra@miradordeluz.test`
- Tenant B: `smoke-bosque-sereno` -> usuario `smoke.bosque@miradordeluz.test`
- En modo multi-tenant, `POST /api/v1/auth` necesita `tenant_slug` confiable; `tenant_id` sigue prohibido.
- DNI compartido para smoke de lookup e isolation: `41000001`
- Fecha ancla para smoke de calendar y daily summary: `2030-04-10`
- Cabanas base tenant A: `SMOKE A | Alerce Familiar`, `SMOKE A | Cipres Pareja`, `SMOKE A | Coihue Grupo`
- Reserva resumen tenant A: `Alerce` check-in `2030-04-10`, `Cipres` check-out `2030-04-10`, `Coihue` pendiente `2030-04-10`, bloqueo `2030-04-15 -> 2030-04-18`
- Fixture secundario critico tenant A: cliente `SMOKE Historial A` con reservas `finished`, `cancelled` y `confirmed`
- Fixture secundario critico tenant A: reserva `[SMOKE:A:ARCHIVED_RELATIONS]` conserva cliente y cabana soft-deleted en detalle historico
- Fixture secundario critico tenant A: `Coihue` agrega pending vencida `2030-04-25 -> 2030-04-27` que NO debe bloquear `availability/{cabin_id}`
- Targets para bajas logicas: cliente `SMOKE Cliente Baja A` y cabana `SMOKE A | Sauce Historial` quedan activos con historia asociada

## Supuestos base de todo E2E

### T1. Headers JSON validos

- Prioridad: `smoke`
- Objetivo: asegurar contrato HTTP minimo entre frontend y API.
- Precondiciones: app levantada.
- Pasos frontend:
  1. Enviar request JSON real desde el cliente.
  2. Reintentar con headers validos e invalidos.
- Backend/API: `POST /api/v1/auth` y cualquier `POST/PUT/PATCH` protegido.
- Validaciones criticas:
  - aceptar `application/json` y `application/json; charset=utf-8`
  - rechazar media types no JSON
- Edge cases:
  - `Accept` con multiples tipos incluyendo JSON
  - `Content-Type` invalido

### T2. Acceso sin token y token invalido

- Prioridad: `smoke`
- Objetivo: evitar acceso parcial o fuga de datos.
- Precondiciones: endpoint protegido disponible.
- Pasos frontend:
  1. Abrir pantalla privada sin sesion.
  2. Reintentar con token invalido.
  3. Reintentar con token viejo despues de cambio de password.
- Backend/API: cualquier ruta bajo `auth:sanctum`.
- Validaciones criticas:
  - responder `401`
  - no devolver payload parcial

### T3. Aislamiento tenant

- Prioridad: `smoke`
- Objetivo: impedir contaminacion cross-tenant.
- Precondiciones: datos equivalentes en tenant A y tenant B.
- Pasos frontend:
  1. Iniciar sesion en tenant A.
  2. Intentar operar recursos del tenant B por ID o payload.
- Backend/API: cabins, clients, pricing, reservations, availability.
- Validaciones criticas:
  - no listar datos ajenos
  - no permitir crear ni editar con relaciones de otro tenant
- Edge cases:
  - IDs exactos
  - payloads anidados
  - filtros y busquedas

## Acceso y perfil

### A1. Registro o login por `POST /auth`

- Prioridad: `smoke`
- Objetivo: permitir alta o ingreso desde una sola UX.
- Precondiciones:
  - email inexistente para registro
  - email existente para login
- Pasos frontend:
  1. Completar email y password.
  2. Si es registro, completar name y password confirmation.
  3. Enviar form.
- Backend/API: `POST /api/v1/auth`.
- Validaciones criticas:
  - distinguir `201` registro vs `200` login
  - devolver token valido
  - rechazar `tenant_id` arbitrario
- Edge cases:
  - password invalida
  - password confirmation distinta
  - multiples tenants sin contexto confiable

### A2. Bootstrap de sesion actual

- Prioridad: `smoke`
- Objetivo: rehidratar frontend al refrescar.
- Precondiciones: token valido.
- Pasos frontend:
  1. Abrir la app autenticada.
  2. Ejecutar bootstrap de usuario.
- Backend/API: `GET /api/v1/auth`.
- Validaciones criticas:
  - devolver usuario autenticado
  - no exponer token en payload

### A3. Logout global

- Prioridad: `smoke`
- Objetivo: cerrar sesion de forma segura.
- Precondiciones: usuario con sesion activa.
- Pasos frontend:
  1. Hacer click en salir.
  2. Intentar usar el token anterior.
- Backend/API: `DELETE /api/v1/auth`.
- Validaciones criticas:
  - revocar tokens activos
  - bloquear uso posterior del token

### A4. Actualizacion de perfil

- Prioridad: `secundario`
- Objetivo: mantener datos del operador.
- Precondiciones: sesion activa.
- Pasos frontend:
  1. Abrir perfil.
  2. Editar nombre o email.
  3. Guardar y refrescar pantalla.
- Backend/API: `GET /api/v1/users/profile`, `PUT /api/v1/users/profile`.
- Validaciones criticas:
  - persistencia correcta
  - contrato estable para refresco de UI
- Edge cases:
  - email invalido
  - email duplicado
  - name corto

### A5. Cambio de password con re-login

- Prioridad: `smoke`
- Objetivo: validar seguridad real del flujo.
- Precondiciones: sesion activa y password actual conocida.
- Pasos frontend:
  1. Abrir pantalla de password.
  2. Enviar actual, nueva y confirmacion.
  3. Reingresar con la nueva password.
- Backend/API: `PUT /api/v1/users/password`, `POST /api/v1/auth`.
- Validaciones criticas:
  - invalidar tokens previos
  - permitir login con nueva password
- Edge cases:
  - password actual incorrecta
  - confirmacion distinta

## Clientes

### C1. Listado con filtros y paginacion

- Prioridad: `secundario`
- Objetivo: operar agenda de clientes.
- Precondiciones: varios clientes cargados.
- Pasos frontend:
  1. Abrir listado.
  2. Filtrar por nombre, DNI o ciudad.
  3. Paginar resultados.
- Backend/API: `GET /api/v1/clients`.
- Validaciones criticas:
  - conteo consistente
  - filtros funcionales
- Edge cases:
  - sin resultados
  - pagina alta
  - combinacion de filtros

### C2. Alta de cliente

- Prioridad: `smoke`
- Objetivo: crear ficha previa a reserva.
- Precondiciones: DNI libre en el tenant.
- Pasos frontend:
  1. Completar form.
  2. Guardar.
  3. Verificar aparicion en listado.
- Backend/API: `POST /api/v1/clients`.
- Validaciones criticas:
  - `name` y `dni` obligatorios
  - DNI unico por tenant
- Edge cases:
  - email invalido
  - DNI duplicado
  - edades fuera de rango razonable

### C3. Detalle de cliente con reservas

- Prioridad: `secundario`
- Objetivo: dar contexto operativo completo.
- Precondiciones: cliente con reservas.
- Pasos frontend:
  1. Abrir detalle de cliente.
  2. Revisar historial relacionado.
- Backend/API: `GET /api/v1/clients/{id}`.
- Validaciones criticas:
  - incluir reservas y contador
  - mantener integridad visual del historial
- Edge cases:
  - cliente inexistente

### C4. Edicion parcial de cliente

- Prioridad: `secundario`
- Objetivo: corregir datos sin romper otros campos.
- Precondiciones: cliente existente.
- Pasos frontend:
  1. Editar subset de campos.
  2. Guardar.
  3. Reabrir detalle.
- Backend/API: `PUT/PATCH /api/v1/clients/{id}`.
- Validaciones criticas:
  - no pisar campos no enviados
  - validar unicidad de DNI si cambia
- Edge cases:
  - DNI de otro cliente
  - datos vacios no permitidos

### C5. Baja logica de cliente

- Prioridad: `secundario`
- Objetivo: limpiar padron sin perder historia.
- Precondiciones: cliente existente.
- Pasos frontend:
  1. Eliminar cliente.
  2. Verificar que desaparezca del listado activo.
  3. Revisar reservas historicas relacionadas.
- Backend/API: `DELETE /api/v1/clients/{id}`.
- Validaciones criticas:
  - soft delete real
  - no romper reservas historicas
- Edge cases:
  - reutilizacion posterior del mismo DNI

### C6. Busqueda exacta por DNI

- Prioridad: `smoke`
- Objetivo: acelerar carga operativa en recepcion o reservas.
- Precondiciones: DNI existente o no existente.
- Pasos frontend:
  1. Buscar cliente por DNI.
  2. Si existe, abrir ficha.
  3. Si no existe, mostrar estado claro.
- Backend/API: `GET /api/v1/clients/dni/{dni}`.
- Validaciones criticas:
  - match exacto
  - `404` claro cuando no existe

## Cabanas y caracteristicas

### B1. CRUD de caracteristicas

- Prioridad: `secundario`
- Objetivo: mantener catalogo de amenities.
- Precondiciones: sesion activa.
- Pasos frontend:
  1. Listar features.
  2. Crear feature.
  3. Editar feature.
  4. Eliminar feature.
  5. Abrir detalle de feature.
- Backend/API: `GET/POST/GET/PUT/PATCH/DELETE /api/v1/features`.
- Validaciones criticas:
  - `name` obligatorio en alta
  - detalle consistente en `GET /features/{id}`
- Edge cases:
  - update parcial
  - recurso inexistente

### B2. Alta de cabana con features

- Prioridad: `smoke`
- Objetivo: crear inventario vendible.
- Precondiciones: features del mismo tenant.
- Pasos frontend:
  1. Completar form de cabana.
  2. Seleccionar amenities.
  3. Guardar.
- Backend/API: `POST /api/v1/cabins`.
- Validaciones criticas:
  - `capacity` valida
  - `feature_ids` solo del tenant actual
- Edge cases:
  - feature inexistente
  - feature de otro tenant
  - campos obligatorios faltantes

### B3. Edicion de cabana y sincronizacion de features

- Prioridad: `secundario`
- Objetivo: mantener inventario actualizado.
- Precondiciones: cabana existente.
- Pasos frontend:
  1. Editar nombre, capacidad o features.
  2. Guardar.
  3. Reabrir detalle.
- Backend/API: `PUT/PATCH /api/v1/cabins/{id}`.
- Validaciones criticas:
  - sincronizacion correcta de relaciones
  - reemplazo limpio de features
- Edge cases:
  - `feature_ids: []`
  - feature de otro tenant

### B4. Listado y detalle de cabanas

- Prioridad: `secundario`
- Objetivo: visualizar inventario operativo.
- Precondiciones: varias cabanas cargadas.
- Pasos frontend:
  1. Abrir listado.
  2. Filtrar por capacidad minima.
  3. Abrir detalle.
- Backend/API: `GET /api/v1/cabins`, `GET /api/v1/cabins/{id}`.
- Validaciones criticas:
  - features embebidas en respuesta
  - filtro de capacidad consistente
- Edge cases:
  - cabana inexistente
  - listado vacio

### B5. Baja logica de cabana

- Prioridad: `secundario`
- Objetivo: retirar inventario sin perder trazabilidad.
- Precondiciones: cabana existente.
- Pasos frontend:
  1. Eliminar cabana.
  2. Verificar que no aparezca en listados activos.
  3. Verificar impacto en historial.
- Backend/API: `DELETE /api/v1/cabins/{id}`.
- Validaciones criticas:
  - soft delete
  - no romper reservas historicas

## Tarifas y precios

### P1. CRUD simple de grupos de precio

- Prioridad: `secundario`
- Objetivo: administrar tarifas base.
- Precondiciones: sesion activa.
- Pasos frontend:
  1. Listar grupos.
  2. Crear grupo.
  3. Editar grupo.
  4. Eliminar grupo.
- Backend/API: `GET/POST/PUT/DELETE /api/v1/price-groups`.
- Validaciones criticas:
  - `priority` default `0`
  - persistencia correcta de `is_default`
- Edge cases:
  - campos faltantes
  - borrado con relaciones asociadas

### P2. Cambio de grupo default

- Prioridad: `smoke`
- Objetivo: asegurar una sola tarifa base por tenant.
- Precondiciones: ya existe un grupo default.
- Pasos frontend:
  1. Marcar otro grupo como default.
  2. Refrescar listado.
- Backend/API: `POST /api/v1/price-groups` o `PUT /api/v1/price-groups/{id}`.
- Validaciones criticas:
  - desactivar el default anterior
  - quedar solo uno activo

### P3. Grupo completo con cabanas, precios y rangos

- Prioridad: `smoke`
- Objetivo: configurar una temporada completa desde una sola pantalla.
- Precondiciones: cabanas existentes.
- Pasos frontend:
  1. Crear grupo completo.
  2. Cargar precios por cabana y cantidad de huespedes.
  3. Cargar date ranges.
  4. Guardar.
  5. Reabrir modo detalle completo.
- Backend/API: `POST /api/v1/price-groups/complete`, `GET /api/v1/price-groups/{id}/complete`, `PUT /api/v1/price-groups/{id}/complete`.
- Validaciones criticas:
  - coherencia entre grupo, `cabins_count` y `prices_count`
  - validacion tenant-aware en relaciones
- Edge cases:
  - fechas invertidas
  - cabana ajena
  - grupo inexistente

### P4. CRUD de rangos de precio

- Prioridad: `secundario`
- Objetivo: ajustar estacionalidad.
- Precondiciones: grupo de precio existente.
- Pasos frontend:
  1. Crear rango.
  2. Editarlo.
  3. Eliminarlo.
  4. Revisar listado.
- Backend/API: `GET/POST/PUT/DELETE /api/v1/price-ranges`.
- Validaciones criticas:
  - `start_date` y `end_date` validos
  - `end_date` posterior a `start_date`
- Edge cases:
  - rangos solapados, hoy permitidos
  - rango en fecha pasada

### P5. Tarifas aplicables para una ventana

- Prioridad: `smoke`
- Objetivo: pintar frontend con tarifa correcta por tramo.
- Precondiciones: grupos y rangos configurados.
- Pasos frontend:
  1. Consultar una ventana de fechas.
  2. Mostrar grupo aplicable por dia o tramo.
- Backend/API: `GET /api/v1/price-ranges/applicable-rates`.
- Validaciones criticas:
  - elegir por prioridad
  - desempatar por `created_at`
  - fallback sin tarifa configurada
- Edge cases:
  - solapamientos
  - semantica inclusiva del `end_date`

### P6. CRUD de precios por cabana y cantidad de huespedes

- Prioridad: `smoke`
- Objetivo: fijar precio exacto vendible.
- Precondiciones: cabana y grupo del mismo tenant.
- Pasos frontend:
  1. Crear combinacion cabana + grupo + huespedes.
  2. Editarla.
  3. Consultar por cabana.
  4. Eliminarla.
- Backend/API: `GET/POST/PUT/DELETE /api/v1/cabin-prices-by-guests`, `GET /api/v1/cabin-prices-by-guests/cabin/{cabinId}`.
- Validaciones criticas:
  - validar `num_guests`
  - impedir relaciones cross-tenant
- Edge cases:
  - combinacion soft-deleted que bloquea recreacion
  - mensajes de validacion inconsistentes

### P7. Calculo de precio reservable

- Prioridad: `smoke`
- Objetivo: cotizar antes de reservar.
- Precondiciones: tarifa exacta configurada y cabana valida.
- Pasos frontend:
  1. Seleccionar cabana, fechas y huespedes.
  2. Pedir calculo.
  3. Mostrar total, seña, saldo y breakdown.
- Backend/API: `POST /api/v1/reservations/calculate-price`.
- Validaciones criticas:
  - total correcto
  - seña al 50 por ciento
  - saldo correcto
  - capacidad maxima validada
- Edge cases:
  - sin tarifa
  - cabin de otro tenant
  - fechas invalidas o pasadas
  - cambios de precio dentro de la estadia

### P8. Cotizacion simple

- Prioridad: `smoke`
- Objetivo: previsualizar valor sin crear reserva.
- Precondiciones: mismas que P7.
- Pasos frontend:
  1. Pedir quote.
  2. Mostrar breakdown y disponibilidad comercial.
- Backend/API: `POST /api/v1/reservations/quote`.
- Validaciones criticas:
  - misma logica funcional que calculate-price
- Edge cases:
  - divergencia de validaciones entre quote y calculate-price

## Reservas y operacion

### R1. Crear reserva normal

- Prioridad: `smoke`
- Objetivo: vender una estadia.
- Precondiciones: disponibilidad y tarifa configuradas.
- Pasos frontend:
  1. Seleccionar cabana, fechas y cantidad de huespedes.
  2. Buscar o cargar cliente.
  3. Confirmar creacion.
- Backend/API: `POST /api/v1/reservations`.
- Validaciones criticas:
  - validar disponibilidad
  - calcular montos automaticamente
  - crear o reutilizar cliente por DNI
  - generar `pending_until`
- Edge cases:
  - cabana ocupada
  - sin tarifa
  - cliente con mismo DNI ya existente
  - cliente soft-deleted

### R2. Crear reserva con huespedes adicionales

- Prioridad: `smoke`
- Objetivo: registrar composicion del grupo.
- Precondiciones: `num_guests` mayor al minimo.
- Pasos frontend:
  1. Completar lista de huespedes.
  2. Guardar reserva.
  3. Reabrir detalle.
- Backend/API: `POST /api/v1/reservations`.
- Validaciones criticas:
  - persistir `guests[]`
  - mostrar detalle correcto
- Edge cases:
  - update posterior que elimina y recrea huespedes

### R3. Crear bloqueo manual

- Prioridad: `smoke`
- Objetivo: bloquear fechas por uso interno o mantenimiento.
- Precondiciones: cabana disponible.
- Pasos frontend:
  1. Crear reserva con `is_blocked`.
  2. Revisar detalle y disponibilidad.
- Backend/API: `POST /api/v1/reservations`.
- Validaciones criticas:
  - cliente tecnico
  - montos en `0`
  - `pending_until` nulo
  - bloqueo efectivo sobre disponibilidad
- Edge cases:
  - multiples bloqueos
  - bloqueo sin tarifa configurada

### R4. Actualizar reserva

- Prioridad: `smoke`
- Objetivo: corregir o replanificar una reserva.
- Precondiciones: reserva no finalizada ni cancelada.
- Pasos frontend:
  1. Editar notas o datos comerciales.
  2. Guardar.
  3. Reabrir detalle.
- Backend/API: `PUT/PATCH /api/v1/reservations/{id}`.
- Validaciones criticas:
  - recalcular si cambian fechas, cabana, bloqueo o huespedes
  - revalidar disponibilidad y capacidad
- Edge cases:
  - mover a cabana con menor capacidad
  - pasar a cabana sin tarifa
  - editar solo notas

### R5. Convertir reserva normal en bloqueo

- Prioridad: `secundario`
- Objetivo: transformar una venta pendiente en ocupacion interna.
- Precondiciones: reserva pendiente.
- Pasos frontend:
  1. Editar reserva.
  2. Activar `is_blocked`.
  3. Guardar.
- Backend/API: `PUT /api/v1/reservations/{id}`.
- Validaciones criticas:
  - pasar a cliente tecnico
  - dejar montos en `0`
  - limpiar `pending_until`

### R6. Convertir bloqueo en reserva normal

- Prioridad: `smoke`
- Objetivo: vender una fecha previamente bloqueada.
- Precondiciones: bloqueo existente y tarifa disponible.
- Pasos frontend:
  1. Editar bloqueo.
  2. Desactivar `is_blocked`.
  3. Cargar cliente real.
  4. Guardar.
- Backend/API: `PUT /api/v1/reservations/{id}`.
- Validaciones criticas:
  - recalcular montos
  - restaurar `pending_until`
  - reemplazar cliente tecnico
- Edge cases:
  - falta cliente
  - sin tarifa

### R7. Confirmar reserva con pago de seña

- Prioridad: `smoke`
- Objetivo: pasar de pendiente a confirmada.
- Precondiciones: reserva pendiente.
- Pasos frontend:
  1. Abrir detalle.
  2. Confirmar pago de seña.
  3. Verificar estado actualizado.
- Backend/API: `POST /api/v1/reservations/{reservation}/confirm`.
- Validaciones criticas:
  - crear pago de deposito
  - status `confirmed`
  - evitar doble confirmacion
- Edge cases:
  - reserva ya confirmada
  - bloqueo con deposito `0`

### R8. Pago anticipado de saldo

- Prioridad: `smoke`
- Objetivo: cobrar el restante antes del check-in.
- Precondiciones: reserva confirmada.
- Pasos frontend:
  1. Registrar pago de saldo.
  2. Refrescar detalle.
- Backend/API: `POST /api/v1/reservations/{reservation}/pay-balance`.
- Validaciones criticas:
  - crear pago correcto
  - impedir pago duplicado

### R9. Check-in

- Prioridad: `smoke`
- Objetivo: iniciar la estadia.
- Precondiciones: reserva confirmada.
- Pasos frontend:
  1. Ejecutar check-in.
  2. Si falta saldo, cobrar en el acto.
  3. Verificar nuevo estado.
- Backend/API: `POST /api/v1/reservations/{reservation}/check-in`.
- Validaciones criticas:
  - status `checked_in`
  - contemplar saldo anticipado o cobro al ingreso

### R10. Check-out

- Prioridad: `smoke`
- Objetivo: cerrar la estadia.
- Precondiciones: reserva `checked_in`.
- Pasos frontend:
  1. Ejecutar check-out.
  2. Refrescar resumen diario y detalle.
- Backend/API: `POST /api/v1/reservations/{reservation}/check-out`.
- Validaciones criticas:
  - status `finished`
  - liberar disponibilidad

### R11. Cancelacion explicita

- Prioridad: `smoke`
- Objetivo: anular reserva sin borrarla del mundo.
- Precondiciones: reserva cancelable.
- Pasos frontend:
  1. Ejecutar accion cancelar.
  2. Verificar detalle y agenda.
- Backend/API: `POST /api/v1/reservations/{reservation}/cancel`.
- Validaciones criticas:
  - status `cancelled`
  - dejar de bloquear disponibilidad
- Edge cases:
  - reserva finalizada
  - reserva ya cancelada

### R12. Eliminacion logica de reserva

- Prioridad: `secundario`
- Objetivo: ocultar registro operativo cuando negocio lo permita.
- Precondiciones: reserva existente.
- Pasos frontend:
  1. Eliminar reserva.
  2. Validar impacto en listados.
- Backend/API: `DELETE /api/v1/reservations/{id}`.
- Validaciones criticas:
  - diferenciar semanticamente delete de cancel

### R13. Listado y detalle de reservas

- Prioridad: `secundario`
- Objetivo: seguimiento operativo diario.
- Precondiciones: varias reservas con estados distintos.
- Pasos frontend:
  1. Abrir listado.
  2. Filtrar por estado y fechas.
  3. Abrir detalle.
- Backend/API: `GET /api/v1/reservations`, `GET /api/v1/reservations/{id}`.
- Validaciones criticas:
  - mostrar guests y payments
  - preservar relaciones historicas con soft delete
- Edge cases:
  - cliente o cabana eliminados logicamente

## Disponibilidad y tablero operativo

### O1. Disponibilidad puntual de una cabana

- Prioridad: `smoke`
- Objetivo: responder si una fecha se puede vender.
- Precondiciones: cabana y rango de fechas.
- Pasos frontend:
  1. Elegir cabana y rango.
  2. Consultar disponibilidad.
- Backend/API: `GET /api/v1/availability`.
- Validaciones criticas:
  - considerar pendientes no vencidas
  - considerar confirmed, checked_in y bloqueos
  - excluir canceladas y finalizadas

### O2. Busqueda de cabanas disponibles

- Prioridad: `smoke`
- Objetivo: ofrecer alternativas al usuario.
- Precondiciones: multiples cabanas con estados distintos.
- Pasos frontend:
  1. Consultar por rango.
  2. Mostrar grilla/lista de disponibles.
- Backend/API: `GET /api/v1/availability`.
- Validaciones criticas:
  - `available_count`
  - contrato de `CabinResource`

### O3. Rangos bloqueados por cabana

- Prioridad: `secundario`
- Objetivo: pintar datepicker o agenda de una cabana.
- Precondiciones: cabana con reservas activas.
- Pasos frontend:
  1. Abrir calendario de una cabana.
  2. Consultar ventana.
- Backend/API: `GET /api/v1/availability/{cabin_id}`.
- Validaciones criticas:
  - devolver `blocked_ranges`
  - excluir pendientes vencidas
- Edge cases:
  - fechas faltantes
  - cabana inexistente

### O4. Calendario global de ocupacion

- Prioridad: `smoke`
- Objetivo: tablero operativo por cabana.
- Precondiciones: varias cabanas con reservas activas.
- Pasos frontend:
  1. Abrir calendario general.
  2. Consultar rango.
  3. Visualizar reservas agrupadas por cabana.
- Backend/API: `GET /api/v1/availability/calendar`.
- Validaciones criticas:
  - incluir `client_name`
  - excluir reservas que no deben bloquear
  - conservar nombre aunque cliente este soft-deleted

### O5. Resumen diario

- Prioridad: `smoke`
- Objetivo: tablero de check-ins, check-outs y pendientes del dia.
- Precondiciones: reservas para hoy o fecha elegida.
- Pasos frontend:
  1. Abrir dashboard.
  2. Consultar fecha actual o manual.
  3. Validar tarjetas y listados.
- Backend/API: `GET /api/v1/daily-summary`.
- Validaciones criticas:
  - `has_events`
  - listas `check_ins`, `check_outs`, `expiring_pending`
  - aislamiento tenant
- Edge cases:
  - fecha invalida
  - dia sin eventos

## Observabilidad frontend

### L1. Envio de logs frontend

- Prioridad: `secundario`
- Objetivo: capturar errores reales del cliente.
- Precondiciones: usuario autenticado.
- Pasos frontend:
  1. Disparar evento `warn` o `error`.
  2. Enviar payload con metadata.
  3. Validar acuse del backend.
- Backend/API: `POST /api/v1/observability/frontend-logs`.
- Validaciones criticas:
  - `level` valido
  - formato ISO8601
  - sanitizacion de secretos
  - respuesta con UUID e `ingested_at`
- Edge cases:
  - payload demasiado grande
  - sin auth
  - rate limit excedido

## Pack minimo de smoke

- `T1`, `T2`, `T3`
- `A1`, `A2`, `A3`, `A5`
- `C2`, `C6`
- `B2`
- `P2`, `P3`, `P5`, `P6`, `P7`, `P8`
- `R1`, `R2`, `R3`, `R4`, `R6`, `R7`, `R8`, `R9`, `R10`, `R11`
- `O1`, `O2`, `O4`, `O5`

## Huecos que conviene cubrir si o si

- `POST /api/v1/reservations/{id}/cancel` existe pero merece E2E dedicado, separado del delete.
- `cabin-prices-by-guests` tiene rutas y reglas fuertes, pero necesita coverage E2E real porque afecta pricing de punta a punta.
- `GET /api/v1/features/{id}` merece flujo de detalle, no solo CRUD superficial.
- La ruta de busqueda por DNI de clientes debe validarse en E2E porque hubo deriva entre documentacion y contrato real.

## Evidencia base del analisis

- `routes/api.php`
- `tests/Feature/Auth/AuthTest.php`
- `tests/Feature/Api/UserApiTest.php`
- `tests/Feature/Api/ClientApiTest.php`
- `tests/Feature/Api/CabinApiTest.php`
- `tests/Feature/Api/PriceGroupApiTest.php`
- `tests/Feature/Api/PriceRangeApiTest.php`
- `tests/Feature/Api/PricingRisksValidationTest.php`
- `tests/Feature/Api/ReservationApiTest.php`
- `tests/Feature/Api/AvailabilityApiTest.php`
- `tests/Feature/Api/DailySummaryApiTest.php`
- `tests/Feature/Api/FrontendObservabilityApiTest.php`
- `CLIENTE_FRONTEND_EXAMPLES.md`
- `FRONTEND_EXAMPLES.md`
- `BACKEND_DOCS.md`
- `analisis-modular/*.md`
