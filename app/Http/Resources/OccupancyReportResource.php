<?php

declare(strict_types=1);

namespace App\Http\Resources;

class OccupancyReportResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'cabin_id' => (int) $this['cabin_id'],
            'cabin_name' => $this['cabin_name'],
            'occupancy_rate' => (float) $this['occupancy_rate'],
            'occupied_nights' => (int) $this['occupied_nights'],
            'total_nights' => (int) $this['total_nights'],
        ];
    }
}
