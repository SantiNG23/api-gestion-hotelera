<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportsReservationsRequest;
use App\Http\Resources\OperationalReservationReportResource;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;

class ReportsReservationController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService
    ) {}

    protected function getAllowedFilters(): array
    {
        return ['status', 'cabin_id', 'search'];
    }

    protected function getDefaultSortField(): string
    {
        return 'check_in_date';
    }

    protected function getDefaultSortOrder(): string
    {
        return 'asc';
    }

    public function index(ReportsReservationsRequest $request): JsonResponse
    {
        $filters = [
            ...$this->getQueryParams($request),
            ...$request->validated(),
        ];
        $report = $this->reportsService->getReservationsReport($filters);

        return $this->successResponse([
            'total' => $report['total'],
            'total_revenue' => $report['total_revenue'],
            'reservations' => OperationalReservationReportResource::collection($report['reservations'])->resolve(),
        ]);
    }
}
