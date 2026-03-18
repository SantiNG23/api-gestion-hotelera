<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use Carbon\Carbon;
use Tests\TestCase;

class ReportsReservationsApiTest extends TestCase
{
    private Cabin $cabinA;

    private Cabin $cabinB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthenticatedUser();

        $this->cabinA = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabana Arrayan',
        ]);

        $this->cabinB = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabana Maiten',
        ]);
    }

    public function test_can_get_reservations_report(): void
    {
        $reservation = $this->createReservation([
            'client_name' => 'Ana Perez',
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-13',
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::parse('2026-04-09 18:00:00'),
            'total_price' => 350,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total',
                'total_revenue',
                'reservations' => [
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
            ],
        ]);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.total_revenue', 0.0);
        $response->assertJsonPath('data.reservations.0.id', $reservation->id);
        $response->assertJsonPath('data.reservations.0.client.name', 'Ana Perez');
        $response->assertJsonPath('data.reservations.0.cabin.name', 'Cabana Arrayan');
        $response->assertJsonPath('data.reservations.0.nights', 3);
    }

    public function test_report_returns_full_collection_without_server_side_pagination(): void
    {
        $this->createReservation(['client_name' => 'Huesped 1', 'check_in_date' => '2026-04-01', 'check_out_date' => '2026-04-02']);
        $this->createReservation(['client_name' => 'Huesped 2', 'check_in_date' => '2026-04-03', 'check_out_date' => '2026-04-04']);
        $this->createReservation(['client_name' => 'Huesped 3', 'check_in_date' => '2026-04-05', 'check_out_date' => '2026-04-06']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'per_page' => 2,
                'page' => 2,
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total', 3);
        $response->assertJsonCount(3, 'data.reservations');
        $response->assertJsonMissingPath('meta.current_page');
    }

    public function test_can_filter_report_by_overlapping_date_range(): void
    {
        $inRange = $this->createReservation([
            'client_name' => 'Dentro de rango',
            'check_in_date' => '2026-04-11',
            'check_out_date' => '2026-04-14',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->createReservation([
            'client_name' => 'Fuera de rango',
            'check_in_date' => '2026-05-01',
            'check_out_date' => '2026-05-03',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-12',
                'end_date' => '2026-04-12',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.reservations.0.id', $inRange->id);
        $response->assertJsonPath('data.reservations.0.nights', 1);
    }

    public function test_can_filter_report_by_cabin_id(): void
    {
        $expected = $this->createReservation([
            'client_name' => 'Cabana objetivo',
            'cabin_id' => $this->cabinB->id,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-12',
        ]);

        $this->createReservation([
            'client_name' => 'Otra cabana',
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-12',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'cabin_id' => $this->cabinB->id,
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.reservations.0.id', $expected->id);
        $response->assertJsonPath('data.reservations.0.cabin.name', 'Cabana Maiten');
    }

    public function test_can_search_report_by_guest_or_cabin(): void
    {
        $expected = $this->createReservation([
            'client_name' => 'Lucia Gomez',
            'dni' => '44556677',
            'cabin_id' => $this->cabinB->id,
            'check_in_date' => '2026-04-15',
            'check_out_date' => '2026-04-18',
        ]);

        $this->createReservation([
            'client_name' => 'Pedro Diaz',
            'dni' => '11223344',
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => '2026-04-15',
            'check_out_date' => '2026-04-18',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'search' => '4455',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.reservations.0.id', $expected->id);
        $response->assertJsonPath('data.reservations.0.client.name', 'Lucia Gomez');
    }

    public function test_report_keeps_tenant_isolation(): void
    {
        $otherTenant = $this->createTenant(['name' => 'Tenant externo']);

        $this->runInTenantContext($otherTenant->id, function () use ($otherTenant): void {
            $otherCabin = Cabin::factory()->create([
                'tenant_id' => $otherTenant->id,
                'name' => 'Cabana Externa',
            ]);

            $otherClient = Client::factory()->create([
                'tenant_id' => $otherTenant->id,
                'name' => 'Cliente Externo',
                'dni' => '90909090',
            ]);

            Reservation::factory()->create([
                'tenant_id' => $otherTenant->id,
                'client_id' => $otherClient->id,
                'cabin_id' => $otherCabin->id,
                'check_in_date' => '2026-04-10',
                'check_out_date' => '2026-04-12',
            ]);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total', 0);
        $response->assertJsonCount(0, 'data.reservations');
    }

    public function test_total_revenue_only_counts_operational_statuses(): void
    {
        $this->createReservation([
            'client_name' => 'Pendiente',
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'total_price' => 100,
        ]);

        $this->createReservation([
            'client_name' => 'Confirmada',
            'status' => Reservation::STATUS_CONFIRMED,
            'total_price' => 200,
        ]);

        $this->createReservation([
            'client_name' => 'Check in',
            'status' => Reservation::STATUS_CHECKED_IN,
            'total_price' => 300,
        ]);

        $this->createReservation([
            'client_name' => 'Finalizada',
            'status' => Reservation::STATUS_FINISHED,
            'total_price' => 400,
        ]);

        $this->createReservation([
            'client_name' => 'Cancelada',
            'status' => Reservation::STATUS_CANCELLED,
            'total_price' => 500,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total', 5);
        $response->assertJsonPath('data.total_revenue', 900.0);
    }

    public function test_total_revenue_is_prorated_to_nights_inside_range(): void
    {
        $this->createReservation([
            'client_name' => 'Reserva larga',
            'status' => Reservation::STATUS_CONFIRMED,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-15',
            'total_price' => 500,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-12',
                'end_date' => '2026-04-13',
            ]));

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.reservations.0.nights', 2);
        $response->assertJsonPath('data.total_revenue', 200.0);
    }

    private function createReservation(array $overrides = []): Reservation
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $overrides['client_name'] ?? 'Cliente Reporte',
            'dni' => $overrides['dni'] ?? fake()->unique()->numerify('########'),
        ]);

        return Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $overrides['cabin_id'] ?? $this->cabinA->id,
            'check_in_date' => $overrides['check_in_date'] ?? '2026-04-10',
            'check_out_date' => $overrides['check_out_date'] ?? '2026-04-12',
            'status' => $overrides['status'] ?? Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => $overrides['pending_until'] ?? now()->addDay(),
            'total_price' => $overrides['total_price'] ?? 200,
            'deposit_amount' => $overrides['deposit_amount'] ?? 100,
            'balance_amount' => $overrides['balance_amount'] ?? 100,
            'notes' => $overrides['notes'] ?? null,
        ]);
    }
}
