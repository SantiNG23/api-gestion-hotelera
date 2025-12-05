<?php

declare(strict_types=1);

namespace App\Http\Resources;

class ReservationPaymentResource extends ApiResource
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
            'amount' => (float) $this->amount,
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at->format('Y-m-d H:i:s'),
        ];
    }
}

