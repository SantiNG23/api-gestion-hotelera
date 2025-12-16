<?php

declare(strict_types=1);

namespace App\Http\Resources;

class ReservationResource extends ApiResource
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
            'client_id' => $this->client_id,
            'cabin_id' => $this->cabin_id,
            'check_in_date' => $this->check_in_date->format('Y-m-d'),
            'check_out_date' => $this->check_out_date->format('Y-m-d'),
            'nights' => $this->nights,
            'total_price' => (float) $this->total_price,
            'deposit_amount' => (float) $this->deposit_amount,
            'balance_amount' => (float) $this->balance_amount,
            'status' => $this->status,
            'pending_until' => $this->pending_until?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,

            // Relaciones
            'client' => $this->whenLoaded('client', fn () => new ClientResource($this->client)),
            'cabin' => $this->whenLoaded('cabin', fn () => new SimpleCabinResource($this->cabin)),
            'guests' => $this->whenLoaded('guests', fn () => ReservationGuestResource::collection($this->guests)),
            'payments' => $this->whenLoaded('payments', fn () => ReservationPaymentResource::collection($this->payments)),

            // Computed
            'has_deposit_paid' => $this->when(
                $this->relationLoaded('payments'),
                fn () => $this->hasDepositPaid()
            ),
            'has_balance_paid' => $this->when(
                $this->relationLoaded('payments'),
                fn () => $this->hasBalancePaid()
            ),
        ];
    }
}
