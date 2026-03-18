<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use Tests\TestCase;

class ReportsHistoryDniApiTest extends TestCase
{
    private Cabin $cabin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthenticatedUser();

        $this->cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabana Historial',
        ]);
    }

    public function test_can_get_history_by_dni(): void
    {
        $guest = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sofia Torres',
            'dni' => '77889900',
        ]);

        Reservation::factory()->finished()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $guest->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => '2026-02-10',
            'check_out_date' => '2026-02-12',
            'total_price' => 250,
        ]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $guest->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => '2026-03-15',
            'check_out_date' => '2026-03-18',
            'total_price' => 360,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/history-dni?'.http_build_query([
                'dni' => '77889900',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'check_in_date',
                    'check_out_date',
                    'total_price',
                    'nights',
                    'cabin_id',
                    'cabin' => ['id', 'name'],
                    'client' => ['id', 'name', 'dni'],
                ],
            ],
        ]);
        $response->assertJsonPath('data.0.client.name', 'Sofia Torres');
        $response->assertJsonPath('data.0.client.dni', '77889900');
        $response->assertJsonPath('data.0.cabin.name', 'Cabana Historial');
        $response->assertJsonPath('data.0.check_in_date', '2026-03-15');
    }

    public function test_history_by_dni_keeps_tenant_isolation(): void
    {
        $otherTenant = $this->createTenant(['name' => 'Tenant externo']);

        $this->runInTenantContext($otherTenant->id, function () use ($otherTenant): void {
            $otherCabin = Cabin::factory()->create([
                'tenant_id' => $otherTenant->id,
                'name' => 'Cabana Externa',
            ]);

            $otherGuest = Client::factory()->create([
                'tenant_id' => $otherTenant->id,
                'name' => 'Invitado Externo',
                'dni' => '77889900',
            ]);

            Reservation::factory()->finished()->create([
                'tenant_id' => $otherTenant->id,
                'client_id' => $otherGuest->id,
                'cabin_id' => $otherCabin->id,
                'check_in_date' => '2026-02-10',
                'check_out_date' => '2026-02-12',
            ]);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/history-dni?'.http_build_query([
                'dni' => '77889900',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonCount(0, 'data');
    }
}
