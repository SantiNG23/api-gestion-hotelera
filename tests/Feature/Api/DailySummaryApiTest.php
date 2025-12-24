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
                'has_events',
                'check_ins' => [
                    '*' => [
                        'id',
                        'client_name',
                        'cabin_name',
                        'check_in_date',
                        'check_out_date',
                        'nights',
                        'total_price',
                        'status',
                        'pending_until',
                    ],
                ],
                'check_outs' => [
                    '*' => [
                        'id',
                        'client_name',
                        'cabin_name',
                        'check_in_date',
                        'check_out_date',
                        'nights',
                        'total_price',
                        'status',
                        'pending_until',
                    ],
                ],
                'expiring_pending' => [
                    '*' => [
                        'id',
                        'client_name',
                        'cabin_name',
                        'check_in_date',
                        'check_out_date',
                        'nights',
                        'total_price',
                        'status',
                        'pending_until',
                    ],
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
        $response->assertJsonPath('data.check_ins.0.cabin_name', $this->cabin->name);
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
        $response->assertJsonPath('data.check_outs.0.cabin_name', $this->cabin->name);
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
        $response->assertJsonPath('data.expiring_pending.0.status', Reservation::STATUS_PENDING_CONFIRMATION);
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
        $response->assertJsonPath('data.check_ins.0.check_in_date', $targetDate->format('Y-m-d'));
        $response->assertJsonCount(1, 'data.check_ins');
    }
}
