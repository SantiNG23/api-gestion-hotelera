<?php

declare(strict_types=1);

namespace App\Http\Resources;

/**
 * Resource para el resumen diario
 */
class DailySummaryResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // $this->resource es el array de datos devuelto por el servicio
        return [
            'date' => $this->resource['date'],
            'has_events' => $this->resource['has_events'],
            'check_ins' => ReservationResource::collection($this->resource['check_ins']),
            'check_outs' => ReservationResource::collection($this->resource['check_outs']),
            'expiring_pending' => ReservationResource::collection($this->resource['expiring_pending']),
            'summary' => $this->resource['summary'],
            'occupancy' => $this->resource['occupancy'],
        ];
    }
}
