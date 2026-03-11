<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReservationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendReservationConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ReservationCreated $event): void
    {
        // El cliente ha realizado una reserva (en el front o el dueño por él)
        // Aquí enviaríamos el mail con los detalles del pago de la seña.
        // TODO(MVP+): implementar email de confirmacion de reserva con plantilla y
        // datos operativos (estado, montos, fechas, instrucciones de pago).
        // TODO(MVP+): separar emails transaccionales de marketing para clientes y
        // respetar consentimiento explicito (opt-in/opt-out) antes de campañas.
        Log::info('Enviando email de confirmación para reserva #'.$event->reservation->id);
    }
}
