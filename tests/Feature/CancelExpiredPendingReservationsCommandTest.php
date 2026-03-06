<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cabin;
use App\Models\Reservation;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CancelExpiredPendingReservationsCommandTest extends TestCase
{
    public function test_command_cancels_expired_pending_reservations(): void
    {
        // Setup
        $auth = $this->createAuthenticatedUser();
        $this->actingAs($auth['user']);

        // Crear una cabaña de prueba
        $cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Crear una reserva expirada
        $expiredReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(1),
        ]);

        // Crear una reserva válida
        $validReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->addHours(48),
        ]);

        // Ejecutar el comando
        $exitCode = Artisan::call('reservations:cancel-expired');

        // Verificaciones
        $this->assertEquals(0, $exitCode);

        // Verificar que la expirada fue cancelada
        $expiredReservation->refresh();
        $this->assertTrue($expiredReservation->isCancelled());

        // Verificar que la válida sigue pendiente
        $validReservation->refresh();
        $this->assertTrue($validReservation->isPendingConfirmation());
    }

    public function test_command_output_shows_cancellation_count(): void
    {
        // Setup
        $auth = $this->createAuthenticatedUser();
        $this->actingAs($auth['user']);

        // Crear una cabaña de prueba
        $cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Crear 3 reservas expiradas
        for ($i = 0; $i < 3; $i++) {
            Reservation::factory()->create([
                'tenant_id' => $this->tenant->id,
                'cabin_id' => $cabin->id,
                'status' => Reservation::STATUS_PENDING_CONFIRMATION,
                'pending_until' => now()->subHours($i + 1),
            ]);
        }

        // Ejecutar el comando
        Artisan::call('reservations:cancel-expired');

        // Verificar que el output contiene el mensaje correcto
        $output = Artisan::output();
        $this->assertStringContainsString('Reservas canceladas: 3', $output);
        $this->assertStringContainsString('Operación completada', $output);
    }

    public function test_command_returns_success_when_no_expired_reservations(): void
    {
        // Setup
        $auth = $this->createAuthenticatedUser();
        $this->actingAs($auth['user']);

        // Ejecutar el comando sin reservas expiradas
        $exitCode = Artisan::call('reservations:cancel-expired');

        // Verificar que fue exitoso
        $this->assertEquals(0, $exitCode);

        // Verificar que el output muestra 0 canceladas
        $output = Artisan::output();
        $this->assertStringContainsString('Reservas canceladas: 0', $output);
    }
}
