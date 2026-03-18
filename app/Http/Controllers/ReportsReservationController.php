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
        $paginator = $report['reservations'];
        $reservations = OperationalReservationReportResource::collection($paginator->getCollection())->resolve();

        return response()->json([
            'success' => true,
            'message' => 'Operación exitosa',
            'data' => [
                'total' => $paginator->total(),
                'total_revenue' => $report['total_revenue'],
                'reservations' => $reservations,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
