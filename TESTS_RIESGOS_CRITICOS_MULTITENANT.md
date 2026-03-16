# Tests de Riesgos Críticos - Módulo Usuarios/Acceso

## 📋 Resumen

Se han creado **7 tests PHPUnit** en `tests/Feature/Auth/AuthTest.php` que validan los riesgos CRÍTICOS encontrados en el análisis del módulo de usuarios/acceso del sistema multi-tenant.

Todos los tests están marcados con `markTestIncomplete()` para documentar qué riesgos necesitan ser validados.

## 🔴 Tests Críticos (Riesgos 11, 12, 15, 16)

### 1. **Riesgo 11 - Email Único Global Viola Multitenant**

```php
test_email_unique_global_blocks_same_email_different_tenants()
```

**Ubicación:** `database/migrations/0001_01_01_000000_create_users_table.php:19`

**Problema:** El índice único en `email` es simple, no compuesto con `tenant_id`

**Escenario:**

- Tenant A: User crea cuenta con `juan@example.com` ✓
- Tenant B: Mismo email intenta registrarse → Debería permitirse (2 usuarios en BD diferentes)
- **Actual:** Falla 422 (viola aislamiento multitenant)

**Líneas de código problemáticas:**

```php
$table->string('email')->unique();  // Debería ser: ->unique(['tenant_id', 'email'])
```

---

### 2. **Riesgo 12 - AuthService Busca Sin Filtrado tenant_id**

```php
test_auth_service_finds_user_without_tenant_filtering()
```

**Ubicación:** `app/Services/AuthService.php:18`, `:44`

**Problema:** Búsquedas globales sin filtrado por `tenant_id`

**Código problemático:**

```php
$user = User::where('email', $email)->first();  // Sin ->where('tenant_id', ...)
```

**Riesgo:** Permite login cruzado entre tenants si hay emails duplicados

**Solución esperada:** Usar trait `BelongsToTenant` en User model o filtrar explícitamente

---

### 3. **Riesgo 15 - User Model NO Usa BelongsToTenant**

```php
test_user_model_missing_belongs_to_tenant_trait()
```

**Ubicación:** `app/Models/User.php` (líneas 1-50)

**Problema:** User NO incluye `use BelongsToTenant`

**Comparativa del proyecto:**

| Modelo | BelongsToTenant |
|--------|-----------------|
| Client | ✅ Sí |
| Cabin | ✅ Sí |
| Reservation | ✅ Sí |
| User | ❌ NO |

**Impacto:**

- Sin scope automático en queries
- Sin asignación automática de `tenant_id` en `creating` event
- Búsquedas directas `User::where(...)` ignoran tenant
- Inconsistencia arquitectónica

---

### 4. **Riesgo 16 - AuthService.createUser() NO Asigna tenant_id**

```php
test_auth_service_creates_user_without_tenant_id()
```

**Ubicación:** `app/Services/AuthService.php:30-37`

**Código problemático:**

```php
public function createUser(array $userData): User
{
    return User::create([
        'name' => $userData['name'],
        'email' => $userData['email'],
        'password' => Hash::make($userData['password']),
        // ❌ tenant_id NO SE ASIGNA → Queda NULL
    ]);
}
```

**Problemas:**

1. `tenant_id` queda NULL en BD
2. AuthService no extiende Service (que sí lo asignaría automáticamente)
3. Usuario registrado no tiene aislamiento = Global
4. Vinculado con riesgos 11 y 15: sin tenant_id, aislamiento es imposible

---

## 🟠 Tests Altos (Riesgos 13, 14)

### 5. **Riesgo 13 - AuthRequest Valida Email Sin Contexto Tenant**

```php
test_auth_request_validates_email_globally_without_tenant_context()
```

**Ubicación:** `app/Http/Requests/AuthRequest.php:23`

**Código problemático:**

```php
if (! User::where('email', $this->email)->exists()) {
    // Aplica reglas de registro
}
```

**Riesgo:** Permite race conditions - dos requests simultáneos pueden pasar validación

---

### 6. **Riesgo 14 - UserRequest ignora tenant en validación unique()**

```php
test_user_request_email_validation_ignores_tenant_isolation()
```

**Ubicación:** `app/Http/Requests/UserRequest.php:25`

**Código problemático:**

```php
Rule::unique('users', 'email')->ignore(Auth::id())
// Debería ser:
// Rule::unique('users', 'email')->ignore(Auth::id())->where('tenant_id', Auth::user()->tenant_id)
```

**Comparativa correcta en ClientRequest:**

```php
Rule::unique('clients', 'dni')->where('tenant_id', $tenantId)  // ✅ Correcto
```

---

## 🔥 Test Integrado (Riesgos 11 + 12 + 15 + 16)

### 7. **Escenario de Login Cruzado Entre Tenants**

```php
test_login_across_tenant_boundaries_due_to_missing_scopes()
```

Demuestra cómo los riesgos se combinan provocando vulnerabilidades críticas:

1. User sin BelongsToTenant (15) → sin scope automático
2. AuthService busca sin tenant context (12) → encuentra usuario global
3. Email único simple (11) → bloquea el mismo email en tenants
4. createUser sin tenant_id (16) → usuario sin aislamiento

---

## 📊 Resultados de Ejecución

```bash
php artisan test tests/Feature/Auth/AuthTest.php

Tests:    7 incomplete, 16 passed (79 assertions)
Duration: 0.29s

✓ Tests existentes: 16 PASSED
… Tests nuevos: 7 INCOMPLETE (TODO validation tests)
```

---

## 🚀 Ejecución de Tests

### Ejecutar todos los tests del módulo Auth

```bash
php artisan test tests/Feature/Auth/AuthTest.php
```

### Ejecutar solo tests de riesgos críticos

```bash
php artisan test tests/Feature/Auth/AuthTest.php --filter "test_email_unique|test_auth_service_finds|test_user_model_missing|test_auth_service_creates"
```

### Ejecutar solo un test específico

```bash
php artisan test tests/Feature/Auth/AuthTest.php --filter "test_email_unique_global"
```

---

## 📝 Notas de Implementación

### Estructura de Cada Test

1. **Documentación:** Ubicación del código problemático
2. **Problema:** Descripción clara del riesgo
3. **Código problemático:** Snippet del código afectado
4. **Escenario:** Paso a paso de cómo se manifiesta el riesgo
5. **Esperado vs Actual:** Comparativa del comportamiento esperado vs real
6. **markTestIncomplete():** Indica que el test valida un riesgo conocido

### Importes Agregados

```php
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
```

---

## 🔗 Referencias

- **Reporte completo:** `analisis-modular/report-acceso-y-perfil-de-usuario.md`
- **Riesgos 11-16:** Sección "Hallazgos/Riesgos" del reporte
- **Trait correcto:** `app/Traits/BelongsToTenant.php`
- **Ejemplo correcto:** `app/Models/Client.php` (usa BelongsToTenant)

---

**Creado:** 9 de marzo de 2026
**Archivo:** `tests/Feature/Auth/AuthTest.php`
