<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    private AvailabilityService $service;
    protected ?Tenant $localTenant = null;
    private ?Cabin $localCabin = null;
    private ?Client $localClient = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService();
        $this->localTenant = Tenant::factory()->create();
        $this->localCabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        $this->localClient = Client::factory()->create(['tenant_id' => $this->localTenant->id]);
    }

    public function test_cabin_is_available_when_no_reservations(): void
    {
        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(3)
        );

        $this->assertTrue($result);
    }

    public function test_cabin_is_unavailable_when_booked(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(5),
        ]);

        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow()->addDays(2),
            Carbon::tomorrow()->addDays(4)
        );

        $this->assertFalse($result);
    }

    public function test_cabin_is_available_before_existing_reservation(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(5),
            'check_out_date' => Carbon::tomorrow()->addDays(10),
        ]);

        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(3)
        );

        $this->assertTrue($result);
    }

    public function test_cabin_is_available_after_existing_reservation(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
        ]);

        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow()->addDays(3), // Checkout anterior = checkin nuevo
            Carbon::tomorrow()->addDays(6)
        );

        $this->assertTrue($result);
    }

    public function test_pending_reservation_blocks_if_not_expired(): void
    {
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::now()->addHours(24), // Aún no vence
        ]);

        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(2)
        );

        $this->assertFalse($result);
    }

    public function test_expired_pending_reservation_does_not_block(): void
    {
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::yesterday(), // Ya venció
        ]);

        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(2)
        );

        $this->assertTrue($result);
    }

    public function test_cancelled_reservation_does_not_block(): void
    {
        Reservation::factory()->cancelled()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
        ]);

        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(2)
        );

        $this->assertTrue($result);
    }

    public function test_finished_reservation_does_not_block(): void
    {
        Reservation::factory()->finished()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
        ]);

        $result = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(2)
        );

        $this->assertTrue($result);
    }

    public function test_can_exclude_reservation_for_edit(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
        ]);

        // Sin excluir, no disponible
        $resultWithout = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(2)
        );

        // Excluyendo la reserva, disponible
        $resultWith = $this->service->isAvailable(
            $this->localCabin->id,
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(2),
            $reservation->id
        );

        $this->assertFalse($resultWithout);
        $this->assertTrue($resultWith);
    }

    public function test_get_available_cabins_returns_only_unbooked(): void
    {
        $cabin2 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        $cabin3 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);

        // Reservar cabin2
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin2->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(5),
        ]);

        $result = $this->service->getAvailableCabins(
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(3)
        );

        $availableIds = $result->pluck('id')->toArray();

        $this->assertContains($this->localCabin->id, $availableIds);
        $this->assertContains($cabin3->id, $availableIds);
        $this->assertNotContains($cabin2->id, $availableIds);
    }

    public function test_get_available_cabins_excludes_inactive(): void
    {
        Cabin::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'is_active' => false,
        ]);

        $result = $this->service->getAvailableCabins(
            Carbon::tomorrow(),
            Carbon::tomorrow()->addDays(3)
        );

        $this->assertEquals(1, $result->count()); // Solo this->cabin
    }

    public function test_get_calendar_days_returns_reservations_grouped_by_cabin(): void
    {
        $cabin2 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);

        // Reserva confirmada para cabaña 1
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::parse('2025-01-02'),
            'check_out_date' => Carbon::parse('2025-01-05'),
        ]);

        // Reserva pending para cabaña 2
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin2->id,
            'check_in_date' => Carbon::parse('2025-01-01'),
            'check_out_date' => Carbon::parse('2025-01-03'),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $result = $this->service->getCalendarDays(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-05')
        );

        $this->assertEquals('2025-01-01', $result['from']);
        $this->assertEquals('2025-01-05', $result['to']);
        $this->assertCount(2, $result['cabins']);

        // Verificar cabaña 1 con reserva confirmada
        $cabin1Data = $result['cabins'][0];
        $this->assertEquals($this->localCabin->id, $cabin1Data['id']);
        $this->assertEquals($this->localCabin->name, $cabin1Data['name']);
        $this->assertCount(1, $cabin1Data['reservations']);

        // Verificar estructura de la reserva
        $reservation = $cabin1Data['reservations'][0];
        $this->assertEquals('2025-01-02', $reservation['check_in_date']);
        $this->assertEquals('2025-01-05', $reservation['check_out_date']);
        $this->assertEquals($this->localClient->name, $reservation['client_name']);
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $reservation['status']);

        // Verificar cabaña 2 con reserva pending
        $cabin2Data = $result['cabins'][1];
        $this->assertEquals($cabin2->id, $cabin2Data['id']);
        $this->assertCount(1, $cabin2Data['reservations']);
        $this->assertEquals(Reservation::STATUS_PENDING_CONFIRMATION, $cabin2Data['reservations'][0]['status']);
    }

    public function test_get_calendar_days_excludes_expired_pending_reservations(): void
    {
        // Reserva pendiente vencida (no debería aparecer)
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::parse('2025-01-02'),
            'check_out_date' => Carbon::parse('2025-01-05'),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::yesterday(),
        ]);

        // Reserva pendiente activa (sí debería aparecer)
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::parse('2025-01-10'),
            'check_out_date' => Carbon::parse('2025-01-12'),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::tomorrow(),
        ]);

        $result = $this->service->getCalendarDays(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-31')
        );

        // Solo debería haber 1 reserva (la activa, no la vencida)
        $cabinData = $result['cabins'][0];
        $this->assertCount(1, $cabinData['reservations']);
        $this->assertEquals('2025-01-10', $cabinData['reservations'][0]['check_in_date']);
    }

    public function test_get_calendar_days_excludes_cancelled_and_finished(): void
    {
        // Reserva cancelada
        Reservation::factory()->cancelled()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::parse('2025-01-02'),
            'check_out_date' => Carbon::parse('2025-01-05'),
        ]);

        // Reserva finalizada
        Reservation::factory()->finished()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::parse('2025-01-10'),
            'check_out_date' => Carbon::parse('2025-01-12'),
        ]);

        $result = $this->service->getCalendarDays(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-31')
        );

        $cabinData = $result['cabins'][0];
        $this->assertCount(0, $cabinData['reservations']);
    }
}
