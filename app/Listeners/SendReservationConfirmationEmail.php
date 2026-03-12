<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReservationCreated;
use App\Models\Reservation;
use App\Tenancy\TenantContext;
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
        app(TenantContext::class)->run($event->tenantId, function () use ($event): void {
            $reservation = Reservation::query()->findOrFail($event->reservationId);

            Log::info('Enviando email de confirmacion para reserva #'.$reservation->id);
        });
    }
}
