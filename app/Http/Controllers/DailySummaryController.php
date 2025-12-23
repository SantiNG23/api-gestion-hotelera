<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Services\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailySummaryController extends Controller
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService
    ) {}

    /**
     * Obtiene el resumen diario
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => ['nullable', 'date'],
            ]);

            $date = $request->date ? Carbon::parse($request->date) : Carbon::today();

            $summary = $this->dailySummaryService->getDailySummary($date);
            $occupancyStats = $this->dailySummaryService->getOccupancyStats($date);

            return $this->successResponse([
                'date' => $summary['date'],
                'has_events' => $summary['has_events'],
                'check_ins' => ReservationResource::collection($summary['check_ins']),
                'check_outs' => ReservationResource::collection($summary['check_outs']),
                'expiring_pending' => ReservationResource::collection($summary['expiring_pending']),
                'summary' => $summary['summary'],
                'occupancy' => $occupancyStats,
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
}

