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
        $params = $this->getQueryParams($request);
        $cabins = $this->cabinService->getCabins($params);

        return $this->paginatedResponse($cabins, CabinResource::class);
    }

    /**
     * Crear cabaña
     */
    public function store(CabinRequest $request): JsonResponse
    {
        $cabin = $this->cabinService->createCabin($request->validated());

        return $this->successResponse(
            new CabinResource($cabin),
            'Cabaña creada exitosamente',
            201
        );
    }

    /**
     * Mostrar cabaña
     */
    public function show(int $id): JsonResponse
    {
        $cabin = $this->cabinService->getCabin($id);

        return $this->successResponse(new CabinResource($cabin));
    }

    /**
     * Actualizar cabaña
     */
    public function update(CabinRequest $request, int $id): JsonResponse
    {
        $cabin = $this->cabinService->updateCabin($id, $request->validated());

        return $this->successResponse(
            new CabinResource($cabin),
            'Cabaña actualizada exitosamente'
        );
    }

    /**
     * Eliminar cabaña
     */
    public function destroy(int $id): JsonResponse
    {
        $this->cabinService->deleteCabin($id);

        return $this->successResponse(null, 'Cabaña eliminada exitosamente');
    }
}

