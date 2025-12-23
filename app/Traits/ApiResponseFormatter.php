<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Resources\ApiCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use JsonSerializable;

/**
 * Trait ApiResponseFormatter
 *
 * Proporciona métodos para formatear y manejar respuestas de la API,
 * incluyendo el manejo de excepciones y la transformación de recursos.
 */
trait ApiResponseFormatter
{
    /**
     * Formatea una respuesta exitosa
     */
    protected function successResponse($data, string $message = 'Operación exitosa', int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data instanceof ResourceCollection) {
            // Unificamos: si es colección, mezclamos data, meta y links al nivel raíz
            $responseData = $data->toArray(request());
            $response = array_merge($response, $responseData);
        } elseif ($data instanceof JsonResource) {
            $response['data'] = $data->toArray(request());
        } else {
            $response['data'] = $data;
        }

        return $this->jsonResponseWithPreserveDecimal($response, $status);
    }

    /**
     * Formatea una respuesta de error
     */
    protected function errorResponse(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Formatea una respuesta paginada
     *
     * @param  LengthAwarePaginator  $paginator  Paginador con los datos
     * @param  string  $resourceClass  Clase del resource a usar
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        // Usamos el sistema de transformación que ya busca si existe una Collection específica
        $collection = $this->transformCollection($paginator, $resourceClass);

        return $this->successResponse($collection);
    }

    /**
     * Maneja errores y las convierte en respuestas JSON apropiadas
     */
    protected function handleError(\Throwable $e, int $defaultCode = 400): JsonResponse
    {
        $data = self::getExceptionResponseData($e, $defaultCode);

        // Logging solo si es un error inesperado (500+)
        if ($data['status'] >= 500) {
            Log::error('Error en la aplicación: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $this->errorResponse($data['message'], $data['status'], $data['errors']);
    }

    /**
     * Mapea una excepción a un formato de respuesta estándar
     * Centralizamos aquí para que bootstrap/app.php y handleError usen lo mismo.
     */
    public static function getExceptionResponseData(\Throwable $e, int $defaultCode = 500): array
    {
        $status = $defaultCode;
        $message = 'Ha ocurrido un error inesperado';
        $errors = [];

        if ($e instanceof ValidationException) {
            $status = 422;
            $message = 'Error de validación';
            $errors = $e->errors();
        } elseif ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            $status = 404;
            $message = 'Recurso no encontrado';
        } elseif ($e instanceof AuthenticationException) {
            $status = 401;
            $message = 'No autenticado';
        } elseif ($e instanceof AuthorizationException || $e instanceof \Illuminate\Auth\Access\AuthorizationException || $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
            $status = 403;
            $message = 'No tienes permisos para realizar esta acción';
        } elseif ($e instanceof MethodNotAllowedHttpException) {
            $status = 405;
            $message = 'Método no permitido';
        } elseif ($e instanceof ThrottleRequestsException) {
            $status = 429;
            $message = 'Demasiadas solicitudes';
        } elseif ($e instanceof QueryException) {
            $status = 500;
            $message = 'Error en base de datos';
            if (config('app.debug')) {
                $errors['sql'] = $e->getMessage();
            }
        } elseif (config('app.debug')) {
            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : $status;
            $message = $e->getMessage() ?: $message;
            $errors = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }

        return [
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    /**
     * Transforma una colección de recursos
     */
    protected function transformCollection(LengthAwarePaginator $collection, ?string $resourceClass = null): ResourceCollection
    {
        $model = $collection->first();
        $collectionClass = $model ? $this->getCollectionClass($model) : ApiCollection::class;

        if ($collectionClass === ApiCollection::class || $collectionClass === ResourceCollection::class) {
            // Si usamos la clase base, nos aseguramos de que use el Resource correcto para los ítems
            $resourceCollection = $resourceClass ? $resourceClass::collection($collection) : new ApiCollection($collection);

            // Si es la anónima de Laravel, la envolvemos en nuestra ApiCollection para tener el formato meta/links
            return ($resourceCollection instanceof ApiCollection) ? $resourceCollection : new ApiCollection($collection);
        }

        return new $collectionClass($collection);
    }

    /**
     * Transforma un recurso individual
     */
    protected function transformResource($resource): JsonResource
    {
        if (!$resource instanceof Model) {
            return new JsonResource($resource);
        }

        $resourceClass = $this->getResourceClass($resource);
        return new $resourceClass($resource);
    }

    /**
     * Obtiene la clase de Resource para un modelo
     */
    protected function getResourceClass(Model $model): string
    {
        $modelName = class_basename($model);
        $resourceClass = "App\\Http\\Resources\\{$modelName}Resource";

        if (!class_exists($resourceClass)) {
            return JsonResource::class;
        }

        return $resourceClass;
    }

    /**
     * Obtiene la clase de Collection para un modelo
     */
    protected function getCollectionClass(Model $model): string
    {
        $modelName = class_basename($model);
        $collectionClass = "App\\Http\\Resources\\{$modelName}Collection";

        if (!class_exists($collectionClass)) {
            return ApiCollection::class;
        }

        return $collectionClass;
    }

    /**
     * Crea una respuesta JSON con JSON_PRESERVE_ZERO_FRACTION
     * para que los floats se serialicen correctamente (300.0 en lugar de 300)
     */
    protected function jsonResponseWithPreserveDecimal(array $data, int $status = 200): JsonResponse
    {
        return response()->json(
            $data,
            $status,
            [],
            JSON_PRESERVE_ZERO_FRACTION
        );
    }
}
