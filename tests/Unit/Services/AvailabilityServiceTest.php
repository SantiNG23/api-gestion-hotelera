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
}

