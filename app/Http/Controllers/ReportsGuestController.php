<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportsGuestsRequest;
use App\Http\Resources\GuestReportCollection;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;

class ReportsGuestController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService
    ) {}

    protected function getAllowedFilters(): array
    {
        return ['query'];
    }

    protected function getDefaultSortField(): string
    {
        return 'last_stay';
    }

    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    public function index(ReportsGuestsRequest $request): JsonResponse
    {
        $guests = $this->clientService->getGuestsReport($this->getQueryParams($request));

        return $this->successResponse(new GuestReportCollection($guests));
    }
}
