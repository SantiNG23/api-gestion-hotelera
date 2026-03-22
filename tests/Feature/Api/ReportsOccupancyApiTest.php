<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use Tests\TestCase;

class ReportsOccupancyApiTest extends TestCase
{
    private Cabin $cabinA;

    private Cabin $cabinB;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthenticatedUser();

        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->cabinA = Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cabana A']);
        $this->cabinB = Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cabana B']);
    }

    public function test_can_get_occupancy_report(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-12',
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinB->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'pending_until' => now()->addDay(),
            'check_in_date' => '2026-04-11',
            'check_out_date' => '2026-04-13',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/occupancy?'.http_build_query([
                'start_date' => '2026-04-10',
                'end_date' => '2026-04-12',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'cabin_id',
                    'cabin_name',
                    'occupancy_rate',
                    'occupied_nights',
                    'total_nights',
                ],
            ],
        ]);
        $response->assertJsonPath('data.0.cabin_name', 'Cabana A');
        $response->assertJsonPath('data.0.occupied_nights', 2);
        $response->assertJsonPath('data.0.total_nights', 3);
        $response->assertJsonPath('data.0.occupancy_rate', 66.67);
        $response->assertJsonPath('data.1.cabin_name', 'Cabana B');
        $response->assertJsonPath('data.1.occupied_nights', 2);
    }

    public function test_can_filter_occupancy_by_cabin(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-12',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/occupancy?'.http_build_query([
                'start_date' => '2026-04-10',
                'end_date' => '2026-04-12',
                'cabin_id' => $this->cabinA->id,
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.cabin_id', $this->cabinA->id);
    }
}
