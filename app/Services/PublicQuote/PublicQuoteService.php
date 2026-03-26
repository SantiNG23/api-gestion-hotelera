<?php

declare(strict_types=1);

namespace App\Services\PublicQuote;

use App\Models\Cabin;
use App\Models\Tenant;
use App\Services\PriceCalculatorService;
use App\Tenancy\TenantContext;

class PublicQuoteService
{
    public function __construct(
        private readonly PriceCalculatorService $priceCalculatorService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function quote(Tenant $tenant, int $cabinId, string $checkInDate, string $checkOutDate, int $numGuests): array
    {
        return $this->tenantContext->run($tenant->id, function () use ($cabinId, $checkInDate, $checkOutDate, $numGuests): array {
            $cabin = Cabin::query()->findOrFail($cabinId);

            return $this->priceCalculatorService->generateReservableQuote(
                $cabin,
                $checkInDate,
                $checkOutDate,
                $numGuests,
            );
        });
    }
}
