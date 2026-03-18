<?php

declare(strict_types=1);

namespace App\Http\Resources;

class ReservationReportResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'guest_name' => $this->client?->name,
            'check_in' => $this->check_in_date?->format('Y-m-d'),
            'check_out' => $this->check_out_date?->format('Y-m-d'),
            'cabin_name' => $this->cabin?->name,
            'status' => $this->status,
            'report_status' => $this->reportStatus(),
            'amount' => (float) $this->total_price,
        ];
    }
}
