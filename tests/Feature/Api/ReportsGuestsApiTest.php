<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use Tests\TestCase;

class ReportsGuestsApiTest extends TestCase
{
    private Cabin $cabin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthenticatedUser();

        $this->cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabana Principal',
        ]);
    }

    public function test_can_get_guests_report(): void
    {
        $guest = $this->createGuest('Ana Perez', '12345678', '1122334455', 'ana@test.com');

        $this->createReservationForGuest($guest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-02-10',
            'check_out_date' => '2026-02-12',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/guests');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'dni',
                    'phone',
                    'email',
                    'visits',
                    'last_stay',
                ],
            ],
        ]);
        $response->assertJsonPath('data.0.id', $guest->id);
        $response->assertJsonPath('data.0.name', 'Ana Perez');
        $response->assertJsonPath('data.0.visits', 1);
        $response->assertJsonPath('data.0.last_stay', '2026-02-10');
    }

    public function test_can_filter_guests_report_by_overlapping_date_range(): void
    {
        $matchingGuest = $this->createGuest('Guest Rango', '33445566');
        $outsideGuest = $this->createGuest('Guest Fuera', '77889911');

        $this->createReservationForGuest($matchingGuest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-03-10',
            'check_out_date' => '2026-03-15',
        ]);

        $this->createReservationForGuest($outsideGuest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-04-01',
            'check_out_date' => '2026-04-03',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/guests?'.http_build_query([
                'start_date' => '2026-03-12',
                'end_date' => '2026-03-12',
            ]));

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingGuest->id);
        $response->assertJsonPath('data.0.name', 'Guest Rango');
    }

    public function test_can_search_guests_report_by_partial_name(): void
    {
        $matchingGuest = $this->createGuest('Maria Gonzalez', '22334455');
        $otherGuest = $this->createGuest('Pedro Ramirez', '99887766');

        $this->createReservationForGuest($matchingGuest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-01-05',
            'check_out_date' => '2026-01-07',
        ]);

        $this->createReservationForGuest($otherGuest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-01-10',
            'check_out_date' => '2026-01-12',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/guests?query=mari');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingGuest->id);
        $response->assertJsonPath('data.0.name', 'Maria Gonzalez');
    }

    public function test_can_search_guests_report_by_partial_dni(): void
    {
        $matchingGuest = $this->createGuest('Lucia Diaz', '44556677');
        $otherGuest = $this->createGuest('Jose Soto', '11223344');

        $this->createReservationForGuest($matchingGuest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-03-02',
            'check_out_date' => '2026-03-04',
        ]);

        $this->createReservationForGuest($otherGuest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-03-08',
            'check_out_date' => '2026-03-10',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/guests?query=5566');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingGuest->id);
        $response->assertJsonPath('data.0.dni', '44556677');
    }

    public function test_guests_report_is_paginated(): void
    {
        foreach ([
            ['Andrea Lopez', '10000001', '2026-01-01'],
            ['Bruno Garcia', '10000002', '2026-01-03'],
            ['Carla Sosa', '10000003', '2026-01-05'],
        ] as [$name, $dni, $date]) {
            $guest = $this->createGuest($name, $dni);

            $this->createReservationForGuest($guest, [
                'status' => Reservation::STATUS_FINISHED,
                'check_in_date' => $date,
                'check_out_date' => date('Y-m-d', strtotime($date.' +2 days')),
            ]);
        }

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/guests?per_page=2&page=2');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 3);
    }

    public function test_guests_report_keeps_tenant_isolation(): void
    {
        $localGuest = $this->createGuest('Guest Local', '55667788');

        $this->createReservationForGuest($localGuest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-04-01',
            'check_out_date' => '2026-04-03',
        ]);

        $otherTenant = $this->createTenant(['name' => 'Tenant externo']);

        $this->runInTenantContext($otherTenant->id, function () use ($otherTenant): void {
            $otherCabin = Cabin::factory()->create([
                'tenant_id' => $otherTenant->id,
                'name' => 'Cabana Externa',
            ]);

            $otherGuest = Client::factory()->create([
                'tenant_id' => $otherTenant->id,
                'name' => 'Guest Externo',
                'dni' => '90909090',
            ]);

            Reservation::factory()->finished()->create([
                'tenant_id' => $otherTenant->id,
                'client_id' => $otherGuest->id,
                'cabin_id' => $otherCabin->id,
                'check_in_date' => '2026-04-05',
                'check_out_date' => '2026-04-07',
            ]);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/guests');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.name', 'Guest Local');
    }

    public function test_guests_report_calculates_visits_and_last_stay_from_real_history(): void
    {
        $guest = $this->createGuest('Sofia Torres', '77889900');

        $this->createReservationForGuest($guest, [
            'status' => Reservation::STATUS_FINISHED,
            'check_in_date' => '2026-01-10',
            'check_out_date' => '2026-01-12',
        ]);

        $this->createReservationForGuest($guest, [
            'status' => Reservation::STATUS_CHECKED_IN,
            'check_in_date' => '2026-03-15',
            'check_out_date' => '2026-03-18',
        ]);

        $this->createReservationForGuest($guest, [
            'status' => Reservation::STATUS_CONFIRMED,
            'check_in_date' => '2026-04-01',
            'check_out_date' => '2026-04-04',
        ]);

        $this->createReservationForGuest($guest, [
            'status' => Reservation::STATUS_CANCELLED,
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-12',
        ]);

        $this->createReservationForGuest($guest, [
            'status' => Reservation::STATUS_CHECKED_IN,
            'check_in_date' => '2026-05-01',
            'check_out_date' => '2026-05-03',
            'is_blocked' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/guests?query=77889900');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $guest->id);
        $response->assertJsonPath('data.0.visits', 2);
        $response->assertJsonPath('data.0.last_stay', '2026-03-15');
    }

    private function createGuest(string $name, string $dni, ?string $phone = null, ?string $email = null): Client
    {
        return Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'dni' => $dni,
            'phone' => $phone,
            'email' => $email,
        ]);
    }

    private function createReservationForGuest(Client $guest, array $overrides = []): Reservation
    {
        return Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $guest->id,
            'cabin_id' => $overrides['cabin_id'] ?? $this->cabin->id,
            'check_in_date' => $overrides['check_in_date'] ?? '2026-01-01',
            'check_out_date' => $overrides['check_out_date'] ?? '2026-01-03',
            'status' => $overrides['status'] ?? Reservation::STATUS_FINISHED,
            'pending_until' => $overrides['pending_until'] ?? null,
            'is_blocked' => $overrides['is_blocked'] ?? false,
            'total_price' => $overrides['total_price'] ?? 100,
            'deposit_amount' => $overrides['deposit_amount'] ?? 50,
            'balance_amount' => $overrides['balance_amount'] ?? 50,
        ]);
    }
}
