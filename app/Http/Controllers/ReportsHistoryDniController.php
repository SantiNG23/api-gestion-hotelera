<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportsHistoryDniRequest;
use App\Http\Resources\OperationalReservationReportResource;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;

class ReportsHistoryDniController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService
    ) {}

    public function index(ReportsHistoryDniRequest $request): JsonResponse
    {
        $reservations = $this->reportsService->getHistoryByDni((string) $request->validated('dni'));

        return $this->successResponse(OperationalReservationReportResource::collection($reservations)->resolve());
    }
}
