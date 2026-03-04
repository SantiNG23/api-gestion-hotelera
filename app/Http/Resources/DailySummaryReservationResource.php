<?php

declare(strict_types=1);

namespace App\Http\Resources;

/**
 * Resource optimizado para mostrar información de reservas en el resumen diario
 */
class DailySummaryReservationResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'client_name' => $this->client ? $this->client->name : 'N/A',
            'cabin_name' => $this->cabin ? $this->cabin->name : 'N/A',
            'check_in_date' => $this->check_in_date->format('Y-m-d'),
            'check_out_date' => $this->check_out_date->format('Y-m-d'),
            'nights' => $this->nights,
            'total_price' => (float) $this->total_price,
            'status' => $this->status,
            'pending_until' => $this->pending_until?->format('Y-m-d H:i:s'),
        ];
    }
}
