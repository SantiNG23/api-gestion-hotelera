<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class CancelExpiredPendingReservations extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'reservations:cancel-expired';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Cancela automáticamente las reservas pendientes que han excedido su límite de tiempo (pending_until)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(ReservationService $reservationService): int
    {
        $this->info('Iniciando cancelación de reservas expiradas...');

        $result = $reservationService->autoCalcellExpiredPending();

        $this->info('Operación completada:');
        $this->info("✓ Reservas canceladas: {$result['cancelled']}");

        if ($result['failed'] > 0) {
            $this->warn("✗ Reservas fallidas: {$result['failed']}");
        }

        return $result['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
