<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DailySummaryResource;
use App\Services\DailySummaryService;
use App\Http\Requests\DailySummaryRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DailySummaryController extends Controller
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService
    ) {}

    /**
     * Obtiene el resumen diario
     */
    public function index(DailySummaryRequest $request): JsonResponse
    {
        $date = $request->date ? Carbon::parse($request->date) : Carbon::today();

        $summary = $this->dailySummaryService->getDailySummary($date);

        return $this->successResponse(new DailySummaryResource($summary));
    }
}

