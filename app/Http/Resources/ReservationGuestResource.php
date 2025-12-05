<?php

declare(strict_types=1);

namespace App\Http\Resources;

class ReservationGuestResource extends ApiResource
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
        ];
    }
}

