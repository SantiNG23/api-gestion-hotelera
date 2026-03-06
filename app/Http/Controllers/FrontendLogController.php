<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFrontendLogRequest;
use App\Services\FrontendLogIngestionService;
use Illuminate\Http\JsonResponse;

class FrontendLogController extends Controller
{
    public function __construct(
        private readonly FrontendLogIngestionService $frontendLogIngestionService
    ) {}

    public function store(StoreFrontendLogRequest $request): JsonResponse
    {
        $log = $this->frontendLogIngestionService->ingest($request->validated(), $request);

        return $this->successResponse([
            'id' => $log->id,
            'ingested_at' => $log->ingested_at?->toJSON(),
        ], 'Log de observabilidad registrado exitosamente', 201);
    }
}
