<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\ReservationCreated;
use App\Listeners\SendReservationConfirmationEmail;
use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendReservationConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_restores_tenant_context_before_loading_the_reservation(): void
    {
        Log::spy();

        $tenant = Tenant::factory()->create();

        $reservation = $this->runInTenantContext($tenant->id, function () use ($tenant): Reservation {
            $cabin = Cabin::factory()->create([
                'tenant_id' => $tenant->id,
            ]);

            $client = Client::factory()->create([
                'tenant_id' => $tenant->id,
            ]);

            return Reservation::factory()->create([
                'tenant_id' => $tenant->id,
                'cabin_id' => $cabin->id,
                'client_id' => $client->id,
            ]);
        });

        $this->setTenantContext(null);

        $listener = new SendReservationConfirmationEmail;
        $listener->handle(new ReservationCreated($reservation->id, $tenant->id));

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Enviando email de confirmacion para reserva #'.$reservation->id);

        $this->assertNull(app(TenantContext::class)->id());
    }
}
