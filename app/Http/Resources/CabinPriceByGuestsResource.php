<?php

declare(strict_types=1);

namespace App\Http\Resources;

class CabinPriceByGuestsResource extends ApiResource
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
            'cabin_id' => $this->cabin_id,
            'price_group_id' => $this->price_group_id,
            'num_guests' => $this->num_guests,
            'price_per_night' => (float) $this->price_per_night,
            'cabin' => $this->whenLoaded('cabin', fn () => new CabinResource($this->cabin)),
            'price_group' => $this->whenLoaded('priceGroup', fn () => new PriceGroupResource($this->priceGroup)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
