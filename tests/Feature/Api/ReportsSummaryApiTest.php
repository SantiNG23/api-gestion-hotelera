<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
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
                'occupancy_rate',
                'nights_sold',
                'total_reservations',
                'operational_revenue',
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
        $response->assertJsonPath('data.occupancy_rate', 0.0);
        $response->assertJsonPath('data.nights_sold', 0);
        $response->assertJsonPath('data.total_reservations', 0);
        $response->assertJsonPath('data.operational_revenue', 0.0);
    }

    public function test_keeps_tenant_isolation(): void
    {
        $otherTenant = $this->createTenant(['name' => 'Tenant externo']);
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCabin = Cabin::factory()->create(['tenant_id' => $otherTenant->id]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
            'cabin_id' => $otherCabin->id,
            'check_in_date' => Carbon::parse('2026-03-10'),
            'check_out_date' => Carbon::parse('2026-03-12'),
            'total_price' => 900,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-12',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.occupancy_rate', 0.0);
        $response->assertJsonPath('data.nights_sold', 0);
        $response->assertJsonPath('data.total_reservations', 0);
        $response->assertJsonPath('data.operational_revenue', 0.0);
    }

    public function test_calculates_report_kpis_for_range(): void
    {
        $startDate = Carbon::today()->addDays(10);
        $endDate = $startDate->copy()->addDays(2);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => $startDate->copy(),
            'check_out_date' => $startDate->copy()->addDays(2),
            'pending_until' => now()->addDay(),
            'total_price' => 300,
        ]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinB->id,
            'check_in_date' => $startDate->copy()->addDay(),
            'check_out_date' => $endDate->copy()->addDay(),
            'total_price' => 500,
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => $startDate->copy()->addDay(),
            'check_out_date' => $endDate->copy()->addDay(),
            'pending_until' => now()->subDay(),
            'total_price' => 400,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.occupancy_rate', 66.67);
        $response->assertJsonPath('data.nights_sold', 2);
        $response->assertJsonPath('data.total_reservations', 3);
        $response->assertJsonPath('data.operational_revenue', 500.0);
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
            'total_price' => 450,
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinB->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => $startDate->copy(),
            'check_out_date' => $startDate->copy()->addDays(2),
            'pending_until' => now()->addDay(),
            'total_price' => 320,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'cabin_id' => $this->cabinA->id,
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.occupancy_rate', 66.67);
        $response->assertJsonPath('data.nights_sold', 2);
        $response->assertJsonPath('data.total_reservations', 1);
        $response->assertJsonPath('data.operational_revenue', 450.0);
    }

    public function test_operational_revenue_is_prorated_to_nights_inside_range(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-15',
            'total_price' => 500,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/summary?'.http_build_query([
                'start_date' => '2026-04-12',
                'end_date' => '2026-04-13',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.nights_sold', 2);
        $response->assertJsonPath('data.operational_revenue', 200.0);
    }
}
