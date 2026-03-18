<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationPayment;
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

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'guest_name',
                    'check_in',
                    'check_out',
                    'cabin_name',
                    'status',
                    'report_status',
                    'amount',
                ],
            ],
        ]);
        $response->assertJsonPath('data.0.id', $reservation->id);
        $response->assertJsonPath('data.0.guest_name', 'Ana Perez');
        $response->assertJsonPath('data.0.cabin_name', 'Cabana Arrayan');
        $response->assertJsonPath('data.0.report_status', 'awaiting_deposit');
        $response->assertJsonPath('data.0.amount', 350.0);
    }

    public function test_report_is_paginated(): void
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

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 3);
    }

    public function test_can_filter_report_by_overlapping_date_range(): void
    {
        $inRange = $this->createReservation([
            'client_name' => 'Dentro de rango',
            'check_in_date' => '2026-04-11',
            'check_out_date' => '2026-04-14',
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

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $inRange->id);
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

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $expected->id);
        $response->assertJsonPath('data.0.cabin_name', 'Cabana Maiten');
    }

    public function test_can_search_report_by_guest_or_cabin(): void
    {
        $expected = $this->createReservation([
            'client_name' => 'Lucia Gomez',
            'cabin_id' => $this->cabinB->id,
            'check_in_date' => '2026-04-15',
            'check_out_date' => '2026-04-18',
        ]);

        $this->createReservation([
            'client_name' => 'Pedro Diaz',
            'cabin_id' => $this->cabinA->id,
            'check_in_date' => '2026-04-15',
            'check_out_date' => '2026-04-18',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'search' => 'lucia',
            ]));

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $expected->id);
        $response->assertJsonPath('data.0.guest_name', 'Lucia Gomez');
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

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    public function test_report_status_mapping_is_stable(): void
    {
        $awaitingDeposit = $this->createReservation([
            'client_name' => 'Pendiente activa',
            'check_in_date' => '2026-04-10',
            'check_out_date' => '2026-04-12',
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->addDay(),
        ]);

        $expiredPending = $this->createReservation([
            'client_name' => 'Pendiente vencida',
            'check_in_date' => '2026-04-11',
            'check_out_date' => '2026-04-13',
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subDay(),
        ]);

        $awaitingBalance = $this->createReservation([
            'client_name' => 'Confirmada sin saldo',
            'check_in_date' => '2026-04-12',
            'check_out_date' => '2026-04-14',
            'status' => Reservation::STATUS_CONFIRMED,
            'pending_until' => null,
        ]);

        $readyForCheckIn = $this->createReservation([
            'client_name' => 'Confirmada paga',
            'check_in_date' => '2026-04-13',
            'check_out_date' => '2026-04-15',
            'status' => Reservation::STATUS_CONFIRMED,
            'pending_until' => null,
        ]);

        ReservationPayment::factory()->deposit()->create([
            'reservation_id' => $readyForCheckIn->id,
            'amount' => $readyForCheckIn->deposit_amount,
        ]);

        ReservationPayment::factory()->balance()->create([
            'reservation_id' => $readyForCheckIn->id,
            'amount' => $readyForCheckIn->balance_amount,
        ]);

        $inHouse = $this->createReservation([
            'client_name' => 'Con check-in',
            'check_in_date' => '2026-04-14',
            'check_out_date' => '2026-04-16',
            'status' => Reservation::STATUS_CHECKED_IN,
            'pending_until' => null,
        ]);

        $checkedOut = $this->createReservation([
            'client_name' => 'Finalizada',
            'check_in_date' => '2026-04-15',
            'check_out_date' => '2026-04-17',
            'status' => Reservation::STATUS_FINISHED,
            'pending_until' => null,
        ]);

        $cancelled = $this->createReservation([
            'client_name' => 'Cancelada',
            'check_in_date' => '2026-04-16',
            'check_out_date' => '2026-04-18',
            'status' => Reservation::STATUS_CANCELLED,
            'pending_until' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reports/reservations?'.http_build_query([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'per_page' => 20,
            ]));

        $this->assertPaginatedResponse($response);

        $statusesById = collect($response->json('data'))
            ->mapWithKeys(fn (array $item): array => [$item['id'] => $item['report_status']])
            ->all();

        $this->assertSame('awaiting_deposit', $statusesById[$awaitingDeposit->id]);
        $this->assertSame('expired_pending_confirmation', $statusesById[$expiredPending->id]);
        $this->assertSame('awaiting_balance', $statusesById[$awaitingBalance->id]);
        $this->assertSame('ready_for_check_in', $statusesById[$readyForCheckIn->id]);
        $this->assertSame('in_house', $statusesById[$inHouse->id]);
        $this->assertSame('checked_out', $statusesById[$checkedOut->id]);
        $this->assertSame('cancelled', $statusesById[$cancelled->id]);
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
