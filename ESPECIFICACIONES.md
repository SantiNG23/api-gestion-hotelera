# Especificaciones del Backend - API Laravel

## Proyecto: Mirador de Luz - Sistema de Reservas de Cabañas

---

## 1. STACK TECNOLÓGICO

### Framework y Versiones
- **Framework**: Laravel 12.x
- **PHP**: 8.2+
- **Base de Datos**: MySQL 8.0+ / PostgreSQL 15+
- **API**: RESTful JSON
- **Autenticación**: Laravel Sanctum (opcional para futuras mejoras)

### Dependencias Principales
```json
{
  "laravel/framework": "^12.0",
  "guzzlehttp/guzzle": "^7.0",
  "laravel/sanctum": "^4.0"
}
```

---

## 2. ARQUITECTURA DE LA API

### Endpoints Requeridos

#### Cabañas
```
GET  /api/cabanas                    # Lista todas las cabañas
GET  /api/cabanas/{slug}             # Detalle de una cabaña específica
```

**Response Estructura:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "slug": "cabana-mirador",
    "nombre": "Cabaña Mirador",
    "descripcion": "...",
    "capacidad": 4,
    "precio_base": 15000,
    "amenities": ["wifi", "cocina", "parrilla"],
    "imagenes": [
      {"url": "...", "orden": 1, "alt": "..."}
    ]
  }
}
```

#### Disponibilidad
```
GET  /api/disponibilidad/{cabana_id}?fecha_inicio=YYYY-MM-DD&fecha_fin=YYYY-MM-DD
```

**Response Estructura:**
```json
{
  "success": true,
  "data": {
    "disponible": true,
    "fechas_bloqueadas": ["2024-01-15", "2024-01-16"],
    "precio_por_noche": 15000
  }
}
```

#### Reservas
```
POST /api/reservas                   # Crear nueva reserva
GET  /api/reservas/{codigo}          # Consultar reserva por código único
PUT  /api/reservas/{codigo}          # Modificar/cancelar reserva
```

**Request Body (POST):**
```json
{
  "cabana_id": 1,
  "fecha_inicio": "2024-01-15",
  "fecha_fin": "2024-01-18",
  "nombre_cliente": "Juan Pérez",
  "email_cliente": "juan@example.com",
  "telefono_cliente": "+54 9 351 123-4567",
  "cantidad_personas": 4,
  "comentarios": "..."
}
```

**Response Estructura:**
```json
{
  "success": true,
  "data": {
    "codigo_reserva": "RES-ABC123",
    "estado": "pendiente",
    "precio_total": 45000,
    "fecha_creacion": "2024-01-10T14:30:00Z"
  },
  "message": "Reserva creada exitosamente"
}
```

#### Health Check
```
GET  /api/health                     # Estado de la API
```

---

## 3. ESTRUCTURA DE BASE DE DATOS

### Tabla: `cabanas`
```sql
CREATE TABLE cabanas (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(100) UNIQUE NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  capacidad_personas INT NOT NULL,
  habitaciones INT NOT NULL,
  banos INT NOT NULL,
  precio_base DECIMAL(10,2) NOT NULL,
  metros_cuadrados INT,
  activa BOOLEAN DEFAULT TRUE,
  orden INT DEFAULT 0,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### Tabla: `amenities_cabana`
```sql
CREATE TABLE amenities_cabana (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  cabana_id BIGINT UNSIGNED,
  nombre VARCHAR(100) NOT NULL,
  icono VARCHAR(50),
  FOREIGN KEY (cabana_id) REFERENCES cabanas(id) ON DELETE CASCADE
);
```

### Tabla: `imagenes_cabana`
```sql
CREATE TABLE imagenes_cabana (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  cabana_id BIGINT UNSIGNED,
  url VARCHAR(255) NOT NULL,
  alt TEXT,
  orden INT DEFAULT 0,
  es_principal BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (cabana_id) REFERENCES cabanas(id) ON DELETE CASCADE
);
```

### Tabla: `reservas`
```sql
CREATE TABLE reservas (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  codigo_reserva VARCHAR(20) UNIQUE NOT NULL,
  cabana_id BIGINT UNSIGNED NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  nombre_cliente VARCHAR(150) NOT NULL,
  email_cliente VARCHAR(150) NOT NULL,
  telefono_cliente VARCHAR(50) NOT NULL,
  cantidad_personas INT NOT NULL,
  precio_total DECIMAL(10,2) NOT NULL,
  estado ENUM('pendiente', 'confirmada', 'cancelada', 'completada') DEFAULT 'pendiente',
  comentarios TEXT,
  deleted_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (cabana_id) REFERENCES cabanas(id),
  INDEX idx_fechas (fecha_inicio, fecha_fin),
  INDEX idx_codigo (codigo_reserva),
  INDEX idx_estado (estado)
);
```

---

## 4. MODELOS ELOQUENT

### Modelo: Cabana
**Ubicación**: `app/Models/Cabana.php`

**Relaciones:**
- `hasMany(AmenityCabana::class)`
- `hasMany(ImagenCabana::class)`
- `hasMany(Reserva::class)`

**Scopes:**
- `scopeActivas($query)` - Solo cabañas activas
- `scopeOrdenadas($query)` - Ordenadas por campo orden

**Accessors:**
- `getPrecioFormateadoAttribute()` - Precio con formato moneda

### Modelo: Reserva
**Ubicación**: `app/Models/Reserva.php`

**Traits:**
- `SoftDeletes`

**Relaciones:**
- `belongsTo(Cabana::class)`

**Scopes:**
- `scopeActivas($query)` - Excluye canceladas
- `scopeEntreFechas($query, $inicio, $fin)` - Reservas en rango
- `scopePorEstado($query, $estado)` - Filtra por estado

**Mutators:**
- Auto-generar `codigo_reserva` antes de crear

---

## 5. CONTROLADORES

### CabanasController
**Ubicación**: `app/Http/Controllers/Api/CabanasController.php`

**Métodos:**
```php
public function index()                    // GET /api/cabanas
public function show(string $slug)         // GET /api/cabanas/{slug}
```

**Responsabilidades:**
- Retornar lista de cabañas activas con imágenes
- Retornar detalle completo con amenities y galería
- Cache de respuestas (opcional)

### DisponibilidadController
**Ubicación**: `app/Http/Controllers/Api/DisponibilidadController.php`

**Métodos:**
```php
public function check(Request $request, int $cabanaId)  // GET /api/disponibilidad/{id}
```

**Lógica:**
1. Validar fechas (inicio < fin, no pasadas)
2. Consultar reservas existentes con solapamiento
3. Calcular días bloqueados
4. Retornar disponibilidad y precio

**Query de Solapamiento:**
```php
$reservas = Reserva::where('cabana_id', $cabanaId)
    ->where('estado', '!=', 'cancelada')
    ->where(function($query) use ($inicio, $fin) {
        $query->whereBetween('fecha_inicio', [$inicio, $fin])
              ->orWhereBetween('fecha_fin', [$inicio, $fin])
              ->orWhere(function($q) use ($inicio, $fin) {
                  $q->where('fecha_inicio', '<=', $inicio)
                    ->where('fecha_fin', '>=', $fin);
              });
    })
    ->exists();
```

### ReservasController
**Ubicación**: `app/Http/Controllers/Api/ReservasController.php`

**Métodos:**
```php
public function store(StoreReservaRequest $request)      // POST /api/reservas
public function show(string $codigo)                     // GET /api/reservas/{codigo}
public function update(UpdateReservaRequest $request, string $codigo)  // PUT /api/reservas/{codigo}
```

**Lógica de Creación:**
1. Validar datos (Form Request)
2. Verificar disponibilidad en transacción
3. Calcular precio total
4. Generar código único
5. Crear reserva
6. Retornar confirmación

**Código Único:**
```php
$codigo = 'RES-' . strtoupper(Str::random(6));
// Verificar que no exista
while (Reserva::where('codigo_reserva', $codigo)->exists()) {
    $codigo = 'RES-' . strtoupper(Str::random(6));
}
```

---

## 6. VALIDACIONES (FORM REQUESTS)

### StoreReservaRequest
**Ubicación**: `app/Http/Requests/StoreReservaRequest.php`

**Reglas:**
```php
public function rules(): array
{
    return [
        'cabana_id' => 'required|exists:cabanas,id',
        'fecha_inicio' => 'required|date|after_or_equal:today',
        'fecha_fin' => 'required|date|after:fecha_inicio',
        'nombre_cliente' => 'required|string|max:150',
        'email_cliente' => 'required|email|max:150',
        'telefono_cliente' => 'required|string|max:50',
        'cantidad_personas' => 'required|integer|min:1',
        'comentarios' => 'nullable|string|max:500'
    ];
}

public function messages(): array
{
    return [
        'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior',
        'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
        // ... más mensajes en español
    ];
}
```

---

## 7. CONFIGURACIÓN

### CORS
**Ubicación**: `config/cors.php`

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:4321')],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => false,
```

### Variables de Entorno (.env)
```env
APP_NAME="Mirador de Luz API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.miradordeluz.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=miradordeluz
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=https://miradordeluz.com
CORS_ALLOWED_ORIGINS="${FRONTEND_URL}"

# Email (futuro)
MAIL_MAILER=smtp
```

---

## 8. SEEDERS

### CabanasSeeder
**Ubicación**: `database/seeders/CabanasSeeder.php`

**Datos requeridos:**
- 4 cabañas completas
- Mínimo 5 imágenes por cabaña
- Amenities variados

**Ejemplo:**
```php
Cabana::create([
    'slug' => 'cabana-mirador',
    'nombre' => 'Cabaña Mirador',
    'descripcion' => 'Vista panorámica al lago San Roque...',
    'capacidad_personas' => 4,
    'habitaciones' => 2,
    'banos' => 1,
    'precio_base' => 15000.00,
    'metros_cuadrados' => 60,
    'activa' => true,
    'orden' => 1
]);
```

---

## 9. MIDDLEWARE

### Middleware Requerido
1. **HandleCors** - Configuración CORS
2. **ValidateJsonApi** - Validar headers JSON
3. **ThrottleRequests** - Rate limiting

**Rate Limiting:**
```php
// RouteServiceProvider o routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/reservas', [ReservasController::class, 'store']);
});
```

---

## 10. RESPUESTAS ESTANDARIZADAS

### Trait: ApiResponse
**Ubicación**: `app/Traits/ApiResponse.php`

```php
trait ApiResponse
{
    protected function successResponse($data, $message = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message
        ], $code);
    }

    protected function errorResponse($message, $errors = null, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}
```

---

## 11. TESTING

### Feature Tests Requeridos
**Ubicación**: `tests/Feature/Api/`

**Tests:**
- `CabanasTest.php` - Listar y mostrar cabañas
- `DisponibilidadTest.php` - Verificar cálculo correcto
- `ReservasTest.php` - CRUD completo de reservas

**Ejemplo:**
```php
public function test_puede_crear_reserva_con_fechas_disponibles()
{
    $cabana = Cabana::factory()->create();
    
    $response = $this->postJson('/api/reservas', [
        'cabana_id' => $cabana->id,
        'fecha_inicio' => now()->addDays(10)->format('Y-m-d'),
        'fecha_fin' => now()->addDays(13)->format('Y-m-d'),
        // ...
    ]);
    
    $response->assertStatus(201)
             ->assertJsonStructure(['success', 'data', 'message']);
}
```

---

## 12. DEPLOYMENT

### Comandos de Despliegue
```bash
# Instalar dependencias
composer install --optimize-autoloader --no-dev

# Configurar entorno
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones
php artisan migrate --force

# Seed inicial (solo primera vez)
php artisan db:seed --class=CabanasSeeder
```

### Servidor Recomendado
- **CPU**: 2 vCPUs
- **RAM**: 2GB
- **Almacenamiento**: 20GB SSD
- **Plataforma**: Railway / Laravel Forge / DigitalOcean

---

## 13. LOGS Y MONITOREO

### Eventos a Loguear
- Creación de reservas
- Errores de disponibilidad
- Intentos de reservas duplicadas
- Errores 500

**Ejemplo:**
```php
Log::info('Reserva creada', [
    'codigo' => $reserva->codigo_reserva,
    'cabana' => $reserva->cabana->nombre,
    'cliente' => $reserva->email_cliente
]);
```

---

## 14. SEGURIDAD

### Checklist de Seguridad
- ✅ Validación de todos los inputs
- ✅ Sanitización de datos
- ✅ Rate limiting en endpoints públicos
- ✅ HTTPS obligatorio en producción
- ✅ Códigos UUID en lugar de IDs incrementales
- ✅ Logs de acciones críticas
- ✅ CORS configurado correctamente

---

## 15. PERFORMANCE

### Optimizaciones
- Eager loading de relaciones
- Índices en campos de búsqueda frecuente
- Cache de respuestas estáticas
- Query optimization

**Ejemplo Eager Loading:**
```php
Cabana::with(['imagenes', 'amenities'])
    ->activas()
    ->ordenadas()
    ->get();
```

---

## RECURSOS ADICIONALES

### Documentación Oficial
- Laravel 11: https://laravel.com/docs/11.x
- Eloquent ORM: https://laravel.com/docs/11.x/eloquent

### Comandos Útiles
```bash
# Crear controlador
php artisan make:controller Api/CabanasController --api

# Crear modelo con migración
php artisan make:model Cabana -m

# Crear Form Request
php artisan make:request StoreReservaRequest

# Crear seeder
php artisan make:seeder CabanasSeeder

# Ejecutar tests
php artisan test
```

