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
        $params = $this->getQueryParams($request);
        $priceGroups = $this->priceGroupService->getPriceGroups($params);

        return $this->paginatedResponse($priceGroups, PriceGroupResource::class);
    }

    /**
     * Crear grupo de precio
     */
    public function store(PriceGroupRequest $request): JsonResponse
    {
        $priceGroup = $this->priceGroupService->createPriceGroup($request->validated());

        return $this->successResponse(
            new PriceGroupResource($priceGroup),
            'Grupo de precio creado exitosamente',
            201
        );
    }

    /**
     * Mostrar grupo de precio
     */
    public function show(int $id): JsonResponse
    {
        $priceGroup = $this->priceGroupService->getPriceGroup($id);

        return $this->successResponse(new PriceGroupResource($priceGroup));
    }

    /**
     * Actualizar grupo de precio
     */
    public function update(PriceGroupRequest $request, int $id): JsonResponse
    {
        $priceGroup = $this->priceGroupService->updatePriceGroup($id, $request->validated());

        return $this->successResponse(
            new PriceGroupResource($priceGroup),
            'Grupo de precio actualizado exitosamente'
        );
    }

    /**
     * Eliminar grupo de precio
     */
    public function destroy(int $id): JsonResponse
    {
        $this->priceGroupService->deletePriceGroup($id);

        return $this->successResponse(null, 'Grupo de precio eliminado exitosamente');
    }
}

