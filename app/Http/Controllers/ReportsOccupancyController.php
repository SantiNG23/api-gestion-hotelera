<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportsOccupancyRequest;
use App\Http\Resources\OccupancyReportResource;
use App\Services\ReportsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReportsOccupancyController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService
    ) {}

    public function index(ReportsOccupancyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $occupancy = $this->reportsService->getOccupancy(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            isset($validated['cabin_id']) ? (int) $validated['cabin_id'] : null,
        );

        return $this->successResponse(OccupancyReportResource::collection(collect($occupancy))->resolve());
    }
}
