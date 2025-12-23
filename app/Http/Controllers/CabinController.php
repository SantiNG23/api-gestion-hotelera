<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CabinRequest;
use App\Http\Resources\CabinResource;
use App\Services\CabinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CabinController extends Controller
{
    public function __construct(
        private readonly CabinService $cabinService
    ) {}

    /**
     * Filtros permitidos para cabañas
     */
    protected function getAllowedFilters(): array
    {
        return ['is_active', 'min_capacity', 'global'];
    }

    /**
     * Listar cabañas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $params = $this->getQueryParams($request);
            $cabins = $this->cabinService->getCabins($params);

            return $this->paginatedResponse($cabins, CabinResource::class);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Crear cabaña
     */
    public function store(CabinRequest $request): JsonResponse
    {
        try {
            $cabin = $this->cabinService->createCabin($request->validated());

            return $this->successResponse(
                $this->transformResource($cabin),
                'Cabaña creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Mostrar cabaña
     */
    public function show(int $id): JsonResponse
    {
        try {
            $cabin = $this->cabinService->getCabin($id);

            return $this->successResponse($this->transformResource($cabin));
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Actualizar cabaña
     */
    public function update(CabinRequest $request, int $id): JsonResponse
    {
        try {
            $cabin = $this->cabinService->updateCabin($id, $request->validated());

            return $this->successResponse(
                $this->transformResource($cabin),
                'Cabaña actualizada exitosamente'
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Eliminar cabaña
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->cabinService->deleteCabin($id);

            return $this->successResponse(null, 'Cabaña eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
}

