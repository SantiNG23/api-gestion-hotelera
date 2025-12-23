---
trigger: glob
globs: app/Http/Controllers/**/*.php
---

# Regla de Controladores

## Regla Principal

**TODOS los controladores deben extender de `App\Http\Controllers\Controller`** para heredar los helpers de respuesta y la lógica de normalización de parámetros a través de `getQueryParams`.

## Responsabilidad y Diseño "Slim"

Los controladores son **delegadores y orquestadores puros**. Su código debe ser minimalista y seguir estos pilares:

- **Validación Automática**: Usar `FormRequest` para que la validación ocurra antes de entrar al método.
- **Sin Lógica de Negocio**: Toda la lógica debe residir en el `Service`. El controlador solo orquesta.
- **Manejo Global de Excepciones**: NO usar `try/catch` para errores estándar (404, 401, 403, 422, 500). El proyecto tiene configurado un handler global en `bootstrap/app.php` que formatea automáticamente estas excepciones al formato JSON esperado.
- **Uso de IDs**: Recibir `int $id` en lugar de inyectar modelos (`Route Model Binding`). Esto garantiza que el `Service` sea el responsable de recuperar el modelo aplicando los filtros de seguridad y multi-tenant correspondientes.

## Estructura de un Controlador CRUD (Pragmática)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        protected readonly TaskService $taskService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $params = $this->getQueryParams($request);
        $tasks = $this->taskService->getTasks($params);

        return $this->paginatedResponse($tasks, TaskResource::class);
    }

    public function store(TaskRequest $request): JsonResponse
    {
        $task = $this->taskService->createTask($request->validated());

        return $this->successResponse(
            new TaskResource($task),
            'Tarea creada correctamente',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->taskService->getTask($id);

        return $this->successResponse(new TaskResource($task));
    }

    public function update(TaskRequest $request, int $id): JsonResponse
    {
        $task = $this->taskService->updateTask($id, $request->validated());

        return $this->successResponse(new TaskResource($task), 'Tarea actualizada');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->taskService->deleteTask($id);

        return $this->successResponse(null, 'Eliminado correctamente');
    }
}
```

## Manejo de Excepciones (Cuándo usar try/catch)

Solo usar `try/catch` en el controlador cuando necesites un comportamiento **específico** que el handler global no proporcione, como:
- Enmascarar un error de integridad de base de datos por un mensaje de negocio amigable (ej: 409 Conflict).
- Loguear información de contexto adicional en canales específicos (ej: log de pagos).
- Retornar códigos de estado HTTP personalizados ante errores de lógica de negocio del service.

## Respuestas: Resources vs Arrays

- **Resources**: Mandatorio para entidades `Eloquent` (`new TaskResource($model)`).
- **Arrays**: Permitido para respuestas de utilidad, reportes, estadísticas o cotizaciones que no mapean directamente a un modelo único.

## Helpers del Base Controller

- `getQueryParams(Request $request)`: Extrae filtros (vía `getAllowedFilters`), orden y paginación.
- `successResponse($data, $message, $status)`: Envuelve la respuesta en el formato `{success: true, data: ..., message: ...}`.
- `paginatedResponse($paginator, $resourceClass)`: Gestiona automáticamente los metadatos y links de paginación de Laravel.
- `errorResponse($message, $status, $errors)`: Para devolver errores manuales de lógica de negocio.
