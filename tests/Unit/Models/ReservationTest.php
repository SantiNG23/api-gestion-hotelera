<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPayment;
use App\Models\Tenant;
use Carbon\Carbon;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    protected ?Tenant $localTenant = null;
    private ?Client $localClient = null;
    private ?Cabin $localCabin = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localTenant = Tenant::factory()->create();
        $this->localClient = Client::factory()->create(['tenant_id' => $this->localTenant->id]);
        $this->localCabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
    }

    public function test_has_client_relationship(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        $this->assertInstanceOf(Client::class, $reservation->client);
        $this->assertEquals($this->localClient->id, $reservation->client->id);
    }

    public function test_has_cabin_relationship(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        $this->assertInstanceOf(Cabin::class, $reservation->cabin);
        $this->assertEquals($this->localCabin->id, $reservation->cabin->id);
    }

    public function test_has_guests_relationship(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        ReservationGuest::factory()->count(2)->create([
            'reservation_id' => $reservation->id,
        ]);

        $this->assertCount(2, $reservation->guests);
    }

    public function test_has_payments_relationship(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        ReservationPayment::factory()->create([
            'reservation_id' => $reservation->id,
            'payment_type' => 'deposit',
        ]);

        $this->assertCount(1, $reservation->payments);
    }

    public function test_is_pending_confirmation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $this->assertTrue($reservation->isPendingConfirmation());
        $this->assertFalse($reservation->isConfirmed());
    }

    public function test_is_confirmed(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        $this->assertTrue($reservation->isConfirmed());
        $this->assertFalse($reservation->isPendingConfirmation());
    }

    public function test_is_checked_in(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        $this->assertTrue($reservation->isCheckedIn());
    }

    public function test_is_pending_expired(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::yesterday(),
        ]);

        $this->assertTrue($reservation->isPendingExpired());
    }

    public function test_blocks_availability_when_confirmed(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        $this->assertTrue($reservation->blocksAvailability());
    }

    public function test_blocks_availability_when_pending_not_expired(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::tomorrow(),
        ]);

        $this->assertTrue($reservation->blocksAvailability());
    }

    public function test_does_not_block_when_pending_expired(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::yesterday(),
        ]);

        $this->assertFalse($reservation->blocksAvailability());
    }

    public function test_does_not_block_when_cancelled(): void
    {
        $reservation = Reservation::factory()->cancelled()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        $this->assertFalse($reservation->blocksAvailability());
    }

    public function test_has_deposit_paid(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
        ]);

        $this->assertFalse($reservation->hasDepositPaid());

        ReservationPayment::factory()->deposit()->create([
            'reservation_id' => $reservation->id,
        ]);

        $reservation->refresh();
        $this->assertTrue($reservation->hasDepositPaid());
    }

    public function test_nights_attribute(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(5),
        ]);

        $this->assertEquals(5, $reservation->nights);
    }
}

