<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use Carbon\Carbon;
use Tests\TestCase;

class ReportsSummaryApiTest extends TestCase
{
    private Client $client;

    private Cabin $cabinA;

    private Cabin $cabinB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthenticatedUser();

        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->cabinA = Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cabana A']);
        $this->cabinB = Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cabana B']);
    }

    public function test_can_get_reports_summary(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-12',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'pending_deposits',
                'scheduled_check_ins',
                'estimated_occupancy',
            ],
        ]);
    }

    public function test_returns_zeroed_kpis_when_range_has_no_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-12',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.pending_deposits', 0);
        $response->assertJsonPath('data.scheduled_check_ins', 0);
        $response->assertJsonPath('data.estimated_occupancy', 0.0);
    }

    public function test_keeps_tenant_isolation(): void
    {
        $otherTenant = $this->createTenant(['name' => 'Tenant externo']);
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCabin = Cabin::factory()->create(['tenant_id' => $otherTenant->id]);

        Reservation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
            'cabin_id' => $otherCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => Carbon::parse('2026-03-10'),
            'check_out_date' => Carbon::parse('2026-03-12'),
        ]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
            'cabin_id' => $otherCabin->id,
            'check_in_date' => Carbon::parse('2026-03-11'),
            'check_out_date' => Carbon::parse('2026-03-13'),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-12',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.pending_deposits', 0);
        $response->assertJsonPath('data.scheduled_check_ins', 0);
        $response->assertJsonPath('data.estimated_occupancy', 0.0);
    }

    public function test_calculates_report_kpis_for_range(): void
    {
        $startDate = Carbon::today()->addDays(10);
        $endDate = $startDate->copy()->addDays(2);

        $pendingReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => $startDate->copy(),
            'check_out_date' => $startDate->copy()->addDays(2),
            'pending_until' => $startDate->copy()->subDay()->setTime(18, 0),
        ]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinB->id,
            'check_in_date' => $startDate->copy()->addDay(),
            'check_out_date' => $endDate->copy()->addDay(),
        ]);

        ReservationPayment::factory()->deposit()->create([
            'reservation_id' => $pendingReservation->id,
            'amount' => $pendingReservation->deposit_amount,
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => $startDate->copy()->addDay(),
            'check_out_date' => $endDate->copy()->addDay(),
            'pending_until' => Carbon::yesterday()->setTime(18, 0),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.pending_deposits', 1);
        $response->assertJsonPath('data.scheduled_check_ins', 1);
        $response->assertJsonPath('data.estimated_occupancy', 66.67);
    }

    public function test_can_filter_summary_by_cabin(): void
    {
        $startDate = Carbon::today()->addDays(10);
        $endDate = $startDate->copy()->addDays(2);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => $startDate->copy(),
            'check_out_date' => $startDate->copy()->addDays(2),
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinB->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => $startDate->copy(),
            'check_out_date' => $startDate->copy()->addDays(2),
            'pending_until' => Carbon::today()->addDays(5)->setTime(18, 0),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'cabin_id' => $this->cabinA->id,
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.pending_deposits', 0);
        $response->assertJsonPath('data.scheduled_check_ins', 1);
        $response->assertJsonPath('data.estimated_occupancy', 66.67);
    }
}
