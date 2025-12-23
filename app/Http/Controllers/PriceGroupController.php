<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PriceGroupRequest;
use App\Http\Resources\PriceGroupResource;
use App\Services\PriceGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceGroupController extends Controller
{
    public function __construct(
        private readonly PriceGroupService $priceGroupService
    ) {}

    /**
     * Filtros permitidos para grupos de precio
     */
    protected function getAllowedFilters(): array
    {
        return ['is_default', 'global'];
    }

    /**
     * Listar grupos de precio
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $params = $this->getQueryParams($request);
            $priceGroups = $this->priceGroupService->getPriceGroups($params);

            return $this->paginatedResponse($priceGroups, PriceGroupResource::class);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Crear grupo de precio
     */
    public function store(PriceGroupRequest $request): JsonResponse
    {
        try {
            $priceGroup = $this->priceGroupService->createPriceGroup($request->validated());

            return $this->successResponse(
                $this->transformResource($priceGroup),
                'Grupo de precio creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Mostrar grupo de precio
     */
    public function show(int $id): JsonResponse
    {
        try {
            $priceGroup = $this->priceGroupService->getPriceGroup($id);

            return $this->successResponse($this->transformResource($priceGroup));
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Actualizar grupo de precio
     */
    public function update(PriceGroupRequest $request, int $id): JsonResponse
    {
        try {
            $priceGroup = $this->priceGroupService->updatePriceGroup($id, $request->validated());

            return $this->successResponse(
                $this->transformResource($priceGroup),
                'Grupo de precio actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Eliminar grupo de precio
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->priceGroupService->deletePriceGroup($id);

            return $this->successResponse(null, 'Grupo de precio eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
}

