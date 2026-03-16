<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class ReservationCreated
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $reservationId,
        public readonly int $tenantId,
    ) {}
}
