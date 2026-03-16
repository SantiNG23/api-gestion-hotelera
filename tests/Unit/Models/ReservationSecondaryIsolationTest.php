<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPayment;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationSecondaryIsolationTest extends TestCase
{
    #[Test]
    public function it_hides_reservation_guests_and_payments_from_other_tenants(): void
    {
        [$tenantA, $reservationA] = $this->createReservationForTenant();
        [$tenantB, $reservationB] = $this->createReservationForTenant();

        $this->setTenantContext($tenantA->id);
        $guestA = ReservationGuest::create([
            'reservation_id' => $reservationA->id,
            'name' => 'Guest A',
            'dni' => '11111111',
        ]);

        $this->setTenantContext($tenantB->id);
        $paymentB = ReservationPayment::create([
            'reservation_id' => $reservationB->id,
            'amount' => 100,
            'payment_type' => ReservationPayment::TYPE_DEPOSIT,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $this->setTenantContext($tenantA->id);

        $this->assertNotNull(ReservationGuest::find($guestA->id));
        $this->assertNull(ReservationPayment::find($paymentB->id));
        $this->assertCount(1, ReservationGuest::all());
        $this->assertCount(0, ReservationPayment::whereKey($paymentB->id)->get());
    }

    #[Test]
    public function it_fails_closed_without_tenant_context(): void
    {
        [, $reservation] = $this->createReservationForTenant();

        $guest = ReservationGuest::create([
            'reservation_id' => $reservation->id,
            'name' => 'Guest A',
            'dni' => '11111111',
        ]);

        $this->setTenantContext(null);

        $this->assertNull(ReservationGuest::find($guest->id));
        $this->assertCount(0, ReservationGuest::all());
    }

    private function createReservationForTenant(): array
    {
        $tenant = Tenant::factory()->create();
        $this->setTenantContext($tenant->id);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $cabin = Cabin::factory()->create(['tenant_id' => $tenant->id]);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $cabin->id,
        ]);

        return [$tenant, $reservation];
    }
}
