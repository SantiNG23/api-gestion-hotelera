<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PriceRangeRequest;
use App\Http\Resources\PriceRangeResource;
use App\Services\PriceRangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceRangeController extends Controller
{
    public function __construct(
        private readonly PriceRangeService $priceRangeService
    ) {}

    /**
     * Filtros permitidos para rangos de precio
     */
    protected function getAllowedFilters(): array
    {
        return ['price_group_id'];
    }

    /**
     * Listar rangos de precio
     */
    public function index(Request $request): JsonResponse
    {
        $params = $this->getQueryParams($request);
        $priceRanges = $this->priceRangeService->getPriceRanges($params);

        return $this->paginatedResponse($priceRanges, PriceRangeResource::class);
    }

    /**
     * Crear rango de precio
     */
    public function store(PriceRangeRequest $request): JsonResponse
    {
        $priceRange = $this->priceRangeService->createPriceRange($request->validated());

        return $this->successResponse(
            new PriceRangeResource($priceRange->load('priceGroup')),
            'Rango de precio creado exitosamente',
            201
        );
    }

    /**
     * Mostrar rango de precio
     */
    public function show(int $id): JsonResponse
    {
        $priceRange = $this->priceRangeService->getPriceRange($id);

        return $this->successResponse(new PriceRangeResource($priceRange));
    }

    /**
     * Actualizar rango de precio
     */
    public function update(PriceRangeRequest $request, int $id): JsonResponse
    {
        $priceRange = $this->priceRangeService->updatePriceRange($id, $request->validated());

        return $this->successResponse(
            new PriceRangeResource($priceRange->load('priceGroup')),
            'Rango de precio actualizado exitosamente'
        );
    }

    /**
     * Eliminar rango de precio
     */
    public function destroy(int $id): JsonResponse
    {
        $this->priceRangeService->deletePriceRange($id);

        return $this->successResponse(null, 'Rango de precio eliminado exitosamente');
    }

    /**
     * Obtiene las tarifas aplicables para un rango de fechas
     * con algoritmo de prioridad (precio ganador)
     *
     * Query parameters:
     * - start_date: Fecha de inicio (Y-m-d)
     * - end_date: Fecha de fin (Y-m-d)
     */
    public function getApplicableRates(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $rates = $this->priceRangeService->getApplicableRates(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return $this->successResponse([
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'rates' => $rates,
        ]);
    }
}

