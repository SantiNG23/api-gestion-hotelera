<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;

class GuestReportResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'email' => $this->email,
            'visits' => (int) ($this->visits ?? 0),
            'last_stay' => $this->last_stay !== null
                ? Carbon::parse($this->last_stay)->format('Y-m-d')
                : null,
        ];
    }
}
