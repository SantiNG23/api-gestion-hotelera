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
        try {
            $params = $this->getQueryParams($request);
            $priceRanges = $this->priceRangeService->getPriceRanges($params);

            return $this->paginatedResponse($priceRanges, PriceRangeResource::class);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Crear rango de precio
     */
    public function store(PriceRangeRequest $request): JsonResponse
    {
        try {
            $priceRange = $this->priceRangeService->createPriceRange($request->validated());

            return $this->successResponse(
                $this->transformResource($priceRange->load('priceGroup')),
                'Rango de precio creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Mostrar rango de precio
     */
    public function show(int $id): JsonResponse
    {
        try {
            $priceRange = $this->priceRangeService->getPriceRange($id);

            return $this->successResponse($this->transformResource($priceRange));
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Actualizar rango de precio
     */
    public function update(PriceRangeRequest $request, int $id): JsonResponse
    {
        try {
            $priceRange = $this->priceRangeService->updatePriceRange($id, $request->validated());

            return $this->successResponse(
                $this->transformResource($priceRange->load('priceGroup')),
                'Rango de precio actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Eliminar rango de precio
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->priceRangeService->deletePriceRange($id);

            return $this->successResponse(null, 'Rango de precio eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Obtiene las tarifas aplicables para un rango de fechas
     * con algoritmo de prioridad (precio ganador)
     */
    public function getApplicableRates(Request $request): JsonResponse
    {
        try {
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
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
}

