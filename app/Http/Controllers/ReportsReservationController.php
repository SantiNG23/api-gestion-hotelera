<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportsReservationsRequest;
use App\Http\Resources\ReservationReportCollection;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

class ReportsReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService
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
        $reservations = $this->reservationService->getReservationsReport($this->getQueryParams($request));

        return $this->successResponse(new ReservationReportCollection($reservations));
    }
}
