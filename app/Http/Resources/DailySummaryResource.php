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
            'has_events' => $this->resource['has_events'],
            'check_ins' => DailySummaryReservationResource::collection($this->resource['check_ins']),
            'check_outs' => DailySummaryReservationResource::collection($this->resource['check_outs']),
            'expiring_pending' => DailySummaryReservationResource::collection($this->resource['expiring_pending']),
        ];
    }
}
