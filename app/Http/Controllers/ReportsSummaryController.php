<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportsSummaryRequest;
use App\Services\DailySummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReportsSummaryController extends Controller
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService
    ) {}

    public function index(ReportsSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $summary = $this->dailySummaryService->getReportsSummary(
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            isset($validated['cabin_id']) ? (int) $validated['cabin_id'] : null,
        );

        return $this->successResponse($summary);
    }
}
