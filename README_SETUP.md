# Setup del Backend - Laravel 12.x

## Instalación Inicial

Como Laravel requiere PHP y Composer, debes ejecutar estos comandos manualmente en tu terminal:

### 1. Instalar Laravel

```bash
cd "/Users/santiagonievaglembocki/Desktop/Proyecto/Mirador de Luz/api-miradordeluz"

# Instalar Laravel 12.x
composer create-project laravel/laravel . "^12.0" --prefer-dist
```

### 2. Configurar Base de Datos

```bash
# Crear base de datos en MySQL
mysql -u root -p

# Dentro de MySQL:
CREATE DATABASE miradordeluz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 3. Configurar Variables de Entorno

Edita el archivo `.env` con estos valores:

```env
APP_NAME="Mirador de Luz API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=miradordeluz
DB_USERNAME=root
DB_PASSWORD=tu_password_aqui

FRONTEND_URL=http://localhost:4321
CORS_ALLOWED_ORIGINS="${FRONTEND_URL}"
```

### 4. Generar Application Key

```bash
php artisan key:generate
```

### 5. Instalar Dependencias Adicionales

```bash
# CORS (ya incluido en Laravel 12)
# Instalar Laravel Sanctum (para futuras autenticaciones)
composer require laravel/sanctum
```

### 6. Configurar CORS

Edita `config/cors.php`:

```php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:4321')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

### 7. Crear Estructura de Directorios

```bash
# Crear directorios necesarios
mkdir -p app/Http/Controllers/Api
mkdir -p app/Http/Requests
mkdir -p app/Traits
mkdir -p database/seeders
```

### 8. Crear Trait ApiResponse

Crear archivo `app/Traits/ApiResponse.php`:

```php
<?php

namespace App\Traits;

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

### 9. Crear Health Check Endpoint

Agregar en `routes/api.php`:

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API funcionando correctamente',
        'timestamp' => now()->toIso8601String()
    ]);
});

// Aquí irán los demás endpoints
```

### 10. Iniciar Servidor

```bash
php artisan serve
```

Visita: http://localhost:8000/api/health

Deberías ver:
```json
{
  "success": true,
  "message": "API funcionando correctamente",
  "timestamp": "2024-11-19T..."
}
```

## Próximos Pasos

Una vez que Laravel esté instalado, debes crear:

1. **Migraciones** para las tablas de base de datos
2. **Modelos** Eloquent (Cabana, Reserva, etc.)
3. **Controladores** de API
4. **Form Requests** para validaciones
5. **Seeders** con datos de prueba

Consulta `ESPECIFICACIONES.md` para los detalles completos de cada componente.

## Comandos Útiles

```bash
# Crear migración
php artisan make:migration create_cabanas_table

# Crear modelo con migración
php artisan make:model Cabana -m

# Crear controlador API
php artisan make:controller Api/CabanasController --api

# Crear Form Request
php artisan make:request StoreReservaRequest

# Crear seeder
php artisan make:seeder CabanasSeeder

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed

# Ejecutar tests
php artisan test

# Limpiar cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Verificación

Para verificar que todo está funcionando:

1. ✅ Servidor Laravel corriendo en puerto 8000
2. ✅ Health check respondiendo
3. ✅ Base de datos conectada
4. ✅ CORS configurado correctamente

## Troubleshooting

### Error: "Access denied for user"
- Verifica las credenciales en `.env`
- Verifica que MySQL esté corriendo

### Error: "CORS policy"
- Verifica `FRONTEND_URL` en `.env`
- Limpia cache: `php artisan config:clear`

### Error: "Class not found"
```bash
composer dump-autoload
```

