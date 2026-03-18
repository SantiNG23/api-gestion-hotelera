<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reservation;

class OperationalReservationReportResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->resource;

        return [
            'id' => $reservation->id,
            'status' => $reservation->status,
            'check_in_date' => $reservation->check_in_date?->format('Y-m-d'),
            'check_out_date' => $reservation->check_out_date?->format('Y-m-d'),
            'total_price' => (float) $reservation->total_price,
            'nights' => (int) ($reservation->getAttribute('report_nights') ?? $reservation->nights),
            'cabin_id' => $reservation->cabin_id,
            'cabin' => $reservation->cabin === null ? null : [
                'id' => $reservation->cabin->id,
                'name' => $reservation->cabin->name,
            ],
            'client' => $reservation->client === null ? null : [
                'id' => $reservation->client->id,
                'name' => $reservation->client->name,
                'dni' => $reservation->client->dni,
            ],
        ];
    }
}
