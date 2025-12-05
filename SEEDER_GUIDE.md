# Demo Data Seeder - Guía de Uso

Este seeder (`DemoDataSeeder`) genera datos de prueba realistas para el frontend, cubriendo todos los escenarios posibles de los modelos: **Clients**, **Cabins** y **Features**.

## Contenido del Seeder

### Clientes (13 registros)

-   **5 clientes activos** con datos completos (name, dni, email, phone, age, city)
-   **3 clientes activos** con datos mínimos (solo name y dni)
-   **2 clientes eliminados** (soft delete, para test de recuperación)
-   **1 cliente específico** "Juan Pérez" con datos conocidos (para test de detalle)
-   **1 cliente específico** "María García" sin email/phone (scenario: datos incompletos)
-   **1 cliente específico eliminado** "Cliente Eliminado" (histórico)

### Características (16 registros)

-   **5 features activas** con datos completos (ej. Pileta, Cochera, WiFi)
-   **2 features activas** con datos mínimos (sin ícono)
-   **2 features inactivas** (temporalmente no disponibles)
-   **1 feature eliminada** (soft delete)
-   **5 features populares específicas** (Piscina, WiFi, AC, Parrilla, Cocina)
-   **1 feature inactiva específica** "Spa (En Mantenimiento)"

### Cabañas (16 registros)

-   **4 cabañas activas** con datos completos
-   **2 cabañas activas** con datos mínimos (sin descripción)
-   **2 cabañas inactivas** (en mantenimiento)
-   **1 cabaña inactiva** con datos mínimos
-   **1 cabaña eliminada** (soft delete)
-   **1 cabaña eliminada e inactiva**
-   **4 cabañas populares específicas** (Cabaña del Bosque, del Lago, de Lujo, Económica)
-   **1 cabaña inactiva específica** "Cabaña en Reparación"

### Relaciones Cabin-Feature

Cada cabaña activa obtiene **2-5 features aleatorias** asociadas, simulando amenidades reales.

## Cómo Ejecutar

### Opción 1: Ejecutar el seeder específico (limpia base de datos)

```bash
php artisan migrate:fresh --seed
```

Esto ejecutará todas las migraciones y luego el `DatabaseSeeder`, que a su vez llamará a `DemoDataSeeder`.

### Opción 2: Ejecutar solo el seeder de demo (sin eliminar datos existentes)

```bash
php artisan db:seed --class=DemoDataSeeder
```

### Opción 3: Ejecutar en un tenant específico (si tienes multi-tenancy)

```bash
php artisan db:seed --class=DemoDataSeeder --env=testing
```

## Escenarios para el Frontend

### 1. **Listado de Clientes**

-   Ver clientes activos (11 total)
-   Filtrar/buscar por nombre, dni, email
-   Verificar que eliminados no aparezcan por default
-   Test de paginación con 13+ registros

### 2. **Detalle de Cliente**

-   Abrir cliente con datos completos (Juan Pérez)
-   Abrir cliente sin email (María García)
-   Abrir cliente con datos mínimos (nombre y dni solamente)
-   Intentar acceder a cliente eliminado (validar error 404 o filtro)

### 3. **Listado de Cabañas**

-   Ver solo cabañas activas (9 total)
-   Ver todas incluyendo inactivas (13 total)
-   Verificar filtro por estado (active/inactive)
-   Ver cabañas por capacidad (2, 4, 8 personas)
-   Verificar relaciones con features (cada cabaña muestra sus amenidades)

### 4. **Detalle de Cabaña**

-   Abrir cabaña popular (ej. "Cabaña del Bosque" con descripción completa)
-   Abrir cabaña con descripción null (datos mínimos)
-   Ver características asociadas (2-5 por cabaña)
-   Verificar disponibilidad (activa vs inactiva)

### 5. **Listado de Características**

-   Ver features activas (13 total)
-   Ver todas incluyendo inactivas (16 total)
-   Filtrar por estado y por cabaña que las contiene
-   Ver features sin ícono (datos mínimos)

### 6. **Crear/Editar**

-   Crear nueva cabaña con datos mínimos y verificar que se persista correctamente
-   Editar cabaña existente (cambiar is_active)
-   Crear cliente con y sin email, phone, age
-   Verificar validaciones de campos requeridos

### 7. **Filtros Avanzados**

-   Listar cabañas activas con capacidad >= 4 personas
-   Listar clientes por ciudad
-   Listar features inactivas (para admin: re-activar)
-   Listar datos eliminados (soft delete) con opción de restaurar

## Estados Disponibles en Factories

Cada factory soporta estados para combinar escenarios:

```php
// Clientes
Client::factory()->create();                    // Datos completos (default)
Client::factory()->minimalData()->create();     // Solo name y dni
Client::factory()->deleted()->create();         // Soft delete

// Cabañas
Cabin::factory()->create();                     // Datos completos
Cabin::factory()->minimalData()->create();      // Sin descripción
Cabin::factory()->inactive()->create();         // is_active = false
Cabin::factory()->deleted()->create();          // Soft delete
Cabin::factory()->inactive()->deleted()->create(); // Combinado

// Features
Feature::factory()->create();                   // Datos completos
Feature::factory()->minimalData()->create();    // Sin icon
Feature::factory()->inactive()->create();       // is_active = false
Feature::factory()->deleted()->create();        // Soft delete
```

## Tips para Tests

1. **Verificar soft deletes**: Usar `->withTrashed()` en queries para incluir eliminados
2. **Filtros por estado**: Las migraciones incluyen índices en `[tenant_id, is_active]` para queries rápidas
3. **Relaciones muchos-a-muchos**: `cabin->features()` devuelve collection sincronizada en seeder
4. **Uniqueness**: DNIs en clientes son únicos por tenant (validado en migración)
5. **Nulls esperados**: Email, phone, age en clientes; description en cabañas; icon en features

## Limpiar y Reseeder

Para empezar de cero:

```bash
php artisan migrate:fresh --seed
```

Para eliminar solo demo data (conservar estructura):

```bash
php artisan db:seed --class=DemoDataSeeder --force
```

## Notas

-   El seeder usa `Tenant::first()` o crea uno nuevo si no existe (para desarrollo local)
-   Todos los datos usan las factories mejoradas con estados `minimalData()`, `inactive()`, `deleted()`
-   Las relaciones cabin-features solo vinculan features activas a cabañas activas (scenario realista)
-   Los nombres y descripciones en español facilitan debugging visual en frontend
