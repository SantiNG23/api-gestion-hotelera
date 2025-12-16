<?php

declare(strict_types=1);

namespace App\Http\Resources;

class SimpleCabinResource extends ApiResource
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
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
        ];
    }
}
