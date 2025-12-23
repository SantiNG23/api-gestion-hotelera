<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * La reserva que se acaba de crear
     */
    public Reservation $reservation;

    /**
     * Create a new event instance.
     */
    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }
}
