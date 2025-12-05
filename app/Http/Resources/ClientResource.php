<?php

declare(strict_types=1);

namespace App\Http\Resources;

class ClientResource extends ApiResource
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
            'dni' => $this->dni,
            'age' => $this->age,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'reservations' => $this->whenLoaded('reservations', function () {
                return ReservationResource::collection($this->reservations);
            }),
            'reservations_count' => $this->when(
                $this->reservations_count !== null,
                $this->reservations_count
            ),
        ];
    }
}
