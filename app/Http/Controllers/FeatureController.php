<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FeatureRequest;
use App\Http\Resources\FeatureResource;
use App\Services\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function __construct(
        private readonly FeatureService $featureService
    ) {}

    /**
     * Filtros permitidos para características
     */
    protected function getAllowedFilters(): array
    {
        return ['is_active', 'global'];
    }

    /**
     * Listar características
     */
    public function index(Request $request): JsonResponse
    {
        $params = $this->getQueryParams($request);
        $features = $this->featureService->getFeatures($params);

        return $this->paginatedResponse($features, FeatureResource::class);
    }

    /**
     * Crear característica
     */
    public function store(FeatureRequest $request): JsonResponse
    {
        $feature = $this->featureService->createFeature($request->validated());

        return $this->successResponse(
            $this->transformResource($feature),
            'Característica creada exitosamente',
            201
        );
    }

    /**
     * Mostrar característica
     */
    public function show(int $id): JsonResponse
    {
        $feature = $this->featureService->getFeature($id);

        return $this->successResponse($this->transformResource($feature));
    }

    /**
     * Actualizar característica
     */
    public function update(FeatureRequest $request, int $id): JsonResponse
    {
        $feature = $this->featureService->updateFeature($id, $request->validated());

        return $this->successResponse(
            $this->transformResource($feature),
            'Característica actualizada exitosamente'
        );
    }

    /**
     * Eliminar característica
     */
    public function destroy(int $id): JsonResponse
    {
        $this->featureService->deleteFeature($id);

        return $this->successResponse(null, 'Característica eliminada exitosamente');
    }
}

