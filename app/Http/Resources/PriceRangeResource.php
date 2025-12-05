<?php

declare(strict_types=1);

namespace App\Http\Resources;

class PriceRangeResource extends ApiResource
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
            'price_group_id' => $this->price_group_id,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'price_group' => $this->whenLoaded('priceGroup', fn () => new PriceGroupResource($this->priceGroup)),
        ];
    }
}

