<?php

declare(strict_types=1);

namespace App\Http\Resources;

class PriceGroupResource extends ApiResource
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
            'name' => $this->name,
            'price_per_night' => (float) $this->price_per_night,
            'is_default' => $this->is_default,
            'price_ranges' => $this->whenLoaded('priceRanges', fn () => PriceRangeResource::collection($this->priceRanges)),
        ];
    }
}

