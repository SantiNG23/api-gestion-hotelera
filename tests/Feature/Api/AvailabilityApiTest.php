<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use Carbon\Carbon;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    private Cabin $cabin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();

        $this->cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_check_cabin_availability(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability?' . http_build_query([
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.is_available', true);
    }

    public function test_cabin_is_unavailable_when_booked(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(5),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability?' . http_build_query([
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(4)->format('Y-m-d'),
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.is_available', false);
    }

    public function test_can_get_available_cabins(): void
    {
        $cabin2 = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $cabin3 = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        // Reservar solo cabin2
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $cabin2->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(5),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability?' . http_build_query([
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            ]));

        $this->assertApiResponse($response);
        // cabin y cabin3 están disponibles (2)
        $response->assertJsonPath('data.available_count', 2);
    }

    public function test_pending_expired_reservations_do_not_block(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        // Crear reserva pendiente ya vencida
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(5),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::yesterday(), // Ya venció
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability?' . http_build_query([
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.is_available', true);
    }

    public function test_requires_dates(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_in_date', 'check_out_date']);
    }

    public function test_can_get_blocked_ranges_for_cabin(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        // Crear varias reservas
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::parse('2025-01-02'),
            'check_out_date' => Carbon::parse('2025-01-05'),
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::parse('2025-01-10'),
            'check_out_date' => Carbon::parse('2025-01-12'),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability/' . $this->cabin->id . '?' . http_build_query([
                'from' => '2025-01-01',
                'to' => '2025-01-31',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.cabin_id', $this->cabin->id);
        $response->assertJsonPath('data.from', '2025-01-01');
        $response->assertJsonPath('data.to', '2025-01-31');
        $response->assertJsonCount(2, 'data.blocked_ranges');

        // Verificar estructura de los rangos bloqueados
        $response->assertJsonPath('data.blocked_ranges.0.from', '2025-01-02');
        $response->assertJsonPath('data.blocked_ranges.0.to', '2025-01-05');
        $response->assertJsonPath('data.blocked_ranges.0.status', Reservation::STATUS_CONFIRMED);
    }

    public function test_blocked_ranges_excludes_expired_pending(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        // Reserva pendiente vencida (no debería aparecer)
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::parse('2025-01-02'),
            'check_out_date' => Carbon::parse('2025-01-05'),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::yesterday(),
        ]);

        // Reserva pendiente activa (sí debería aparecer)
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::parse('2025-01-10'),
            'check_out_date' => Carbon::parse('2025-01-12'),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::tomorrow(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability/' . $this->cabin->id . '?' . http_build_query([
                'from' => '2025-01-01',
                'to' => '2025-01-31',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonCount(1, 'data.blocked_ranges');
        $response->assertJsonPath('data.blocked_ranges.0.from', '2025-01-10');
    }

    public function test_can_get_calendar_days(): void
    {
        $cabin2 = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        // Reserva confirmada para cabaña 1
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::parse('2025-01-02'),
            'check_out_date' => Carbon::parse('2025-01-05'),
        ]);

        // Reserva pending para cabaña 2
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $cabin2->id,
            'check_in_date' => Carbon::parse('2025-01-01'),
            'check_out_date' => Carbon::parse('2025-01-03'),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability/calendar?' . http_build_query([
                'from' => '2025-01-01',
                'to' => '2025-01-05',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.from', '2025-01-01');
        $response->assertJsonPath('data.to', '2025-01-05');
        $response->assertJsonCount(2, 'data.cabins');

        // Verificar estructura del primer día de la primera cabaña (debería ser libre)
        $response->assertJsonPath('data.cabins.0.id', $this->cabin->id);
        $response->assertJsonPath('data.cabins.0.days.0.date', '2025-01-01');
        $response->assertJsonPath('data.cabins.0.days.0.status', 'free');

        // Verificar segundo día (2025-01-02, debería estar confirmada)
        $response->assertJsonPath('data.cabins.0.days.1.date', '2025-01-02');
        $response->assertJsonPath('data.cabins.0.days.1.status', Reservation::STATUS_CONFIRMED);
        $response->assertJsonPath('data.cabins.0.days.1.reservation_id', 1);
    }

    public function test_calendar_days_requires_dates(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability/calendar');

        $response->assertStatus(422);
    }

    public function test_blocked_ranges_requires_cabin(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability/999?' . http_build_query([
                'from' => '2025-01-01',
                'to' => '2025-01-31',
            ]));

        $response->assertStatus(404);
    }

    public function test_blocked_ranges_requires_dates(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/availability/' . $this->cabin->id);

        $response->assertStatus(422);
    }
}

