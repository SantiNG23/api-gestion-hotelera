<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use Carbon\Carbon;
use Tests\TestCase;

class DailySummaryApiTest extends TestCase
{
    private Client $client;
    private Cabin $cabin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();

        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_get_daily_summary(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/daily-summary');

        $this->assertApiResponse($response);
        $response->assertJsonStructure([
            'data' => [
                'date',
                'has_events',
                'check_ins',
                'check_outs',
                'expiring_pending',
                'summary' => [
                    'check_ins_count',
                    'check_outs_count',
                    'expiring_pending_count',
                ],
                'occupancy' => [
                    'occupied_cabins',
                    'total_cabins',
                    'occupancy_rate',
                ],
            ],
        ]);
    }

    public function test_shows_check_ins_for_today(): void
    {
        // Reserva confirmada con check-in hoy
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(3),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/daily-summary');

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.has_events', true);
        $response->assertJsonPath('data.summary.check_ins_count', 1);
    }

    public function test_shows_check_outs_for_today(): void
    {
        // Reserva con check-out hoy
        Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::today()->subDays(3),
            'check_out_date' => Carbon::today(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/daily-summary');

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.has_events', true);
        $response->assertJsonPath('data.summary.check_outs_count', 1);
    }

    public function test_shows_expiring_pending_reservations(): void
    {
        // Reserva pendiente que vence hoy
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::today()->setHour(18),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/daily-summary');

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.has_events', true);
        $response->assertJsonPath('data.summary.expiring_pending_count', 1);
    }

    public function test_has_events_false_when_no_events(): void
    {
        // No crear ninguna reserva

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/daily-summary');

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.has_events', false);
    }

    public function test_can_get_summary_for_specific_date(): void
    {
        $targetDate = Carbon::tomorrow();

        // Reserva con check-in mañana
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $targetDate,
            'check_out_date' => $targetDate->copy()->addDays(2),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/daily-summary?date=' . $targetDate->format('Y-m-d'));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.date', $targetDate->format('Y-m-d'));
        $response->assertJsonPath('data.summary.check_ins_count', 1);
    }

    public function test_shows_occupancy_stats(): void
    {
        // Crear 3 cabañas activas
        Cabin::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        // Reservar 1 cabaña para hoy
        Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::today()->subDay(),
            'check_out_date' => Carbon::today()->addDay(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/daily-summary');

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.occupancy.total_cabins', 3);
        $response->assertJsonPath('data.occupancy.occupied_cabins', 1);
    }
}

