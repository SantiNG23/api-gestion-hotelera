<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CabinPriceByGuestsRequest;
use App\Http\Resources\CabinPriceByGuestsResource;
use App\Services\CabinPriceByGuestsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CabinPriceByGuestsController extends Controller
{
    public function __construct(
        private readonly CabinPriceByGuestsService $cabinPriceByGuestsService
    ) {}

    /**
     * Filtros permitidos para precios de cabaña por cantidad de huéspedes
     */
    protected function getAllowedFilters(): array
    {
        return ['cabin_id', 'price_group_id', 'num_guests'];
    }

    /**
     * Listar precios de cabañas por cantidad de huéspedes
     */
    public function index(Request $request): JsonResponse
    {
        $params = $this->getQueryParams($request);
        $prices = $this->cabinPriceByGuestsService->getCabinPricesByGuests($params);

        return $this->paginatedResponse($prices, CabinPriceByGuestsResource::class);
    }

    /**
     * Crear precio de cabaña por cantidad de huéspedes
     */
    public function store(CabinPriceByGuestsRequest $request): JsonResponse
    {
        $price = $this->cabinPriceByGuestsService->createCabinPriceByGuests($request->validated());

        return $this->successResponse(
            new CabinPriceByGuestsResource($price),
            'Precio de cabaña por cantidad de huéspedes creado exitosamente',
            201
        );
    }

    /**
     * Mostrar precio de cabaña por cantidad de huéspedes
     */
    public function show(int $id): JsonResponse
    {
        $price = $this->cabinPriceByGuestsService->getCabinPriceByGuests($id);

        return $this->successResponse(new CabinPriceByGuestsResource($price));
    }

    /**
     * Actualizar precio de cabaña por cantidad de huéspedes
     */
    public function update(CabinPriceByGuestsRequest $request, int $id): JsonResponse
    {
        $price = $this->cabinPriceByGuestsService->updateCabinPriceByGuests($id, $request->validated());

        return $this->successResponse(
            new CabinPriceByGuestsResource($price),
            'Precio de cabaña por cantidad de huéspedes actualizado exitosamente'
        );
    }

    /**
     * Eliminar precio de cabaña por cantidad de huéspedes
     */
    public function destroy(int $id): JsonResponse
    {
        $this->cabinPriceByGuestsService->deleteCabinPriceByGuests($id);

        return $this->successResponse(null, 'Precio de cabaña por cantidad de huéspedes eliminado exitosamente');
    }

    /**
     * Listar precios de una cabaña específica
     */
    public function byCabin(int $cabinId, Request $request): JsonResponse
    {
        $params = $this->getQueryParams($request);
        $prices = $this->cabinPriceByGuestsService->getPricesByCabin($cabinId, $params);

        return $this->paginatedResponse($prices, CabinPriceByGuestsResource::class);
    }
}
