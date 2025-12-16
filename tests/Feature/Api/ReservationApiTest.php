<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\PriceGroup;
use App\Models\Reservation;
use Carbon\Carbon;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    private Client $client;
    private Cabin $cabin;
    private PriceGroup $priceGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();

        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->priceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_per_night' => 100,
            'is_default' => true,
        ]);
    }

    public function test_can_list_reservations(): void
    {
        Reservation::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reservations');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_reservation(): void
    {
        $data = [
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'notes' => 'Reserva de prueba',
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_confirmation')
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.cabin_id', $this->cabin->id);

        // Verificar que se calculó el precio (3 noches x 100 = 300)
        $response->assertJsonPath('data.total_price', 300.0);
        $response->assertJsonPath('data.deposit_amount', 150.0);
        $response->assertJsonPath('data.balance_amount', 150.0);
    }

    public function test_can_create_reservation_with_guests(): void
    {
        $data = [
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'guests' => [
                ['name' => 'Huésped 1', 'dni' => '11111111'],
                ['name' => 'Huésped 2', 'dni' => '22222222', 'age' => 25],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations', $data);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.guests');
    }

    public function test_cannot_create_reservation_with_overlapping_dates(): void
    {
        // Crear una reserva existente
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(5),
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        // Intentar crear reserva solapada
        $data = [
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations', $data);

        $response->assertStatus(422);
    }

    public function test_cannot_create_reservation_without_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id', 'cabin_id', 'check_in_date', 'check_out_date']);
    }

    public function test_can_show_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/reservations/{$reservation->id}");

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.id', $reservation->id);
        $response->assertJsonStructure([
            'data' => ['client', 'cabin', 'guests', 'payments'],
        ]);
    }

    public function test_can_confirm_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/reservations/{$reservation->id}/confirm", [
                'payment_method' => 'efectivo',
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.status', 'confirmed');
        $response->assertJsonPath('data.has_deposit_paid', true);
    }

    public function test_cannot_confirm_already_confirmed_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/reservations/{$reservation->id}/confirm");

        $response->assertStatus(422);
    }

    public function test_can_check_in_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/reservations/{$reservation->id}/check-in", [
                'payment_method' => 'transferencia',
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.status', 'checked_in');
        $response->assertJsonPath('data.has_balance_paid', true);
    }

    public function test_cannot_check_in_pending_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/reservations/{$reservation->id}/check-in");

        $response->assertStatus(422);
    }

    public function test_can_check_out_reservation(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/reservations/{$reservation->id}/check-out");

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.status', 'finished');
    }

    public function test_can_cancel_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/reservations/{$reservation->id}/cancel");

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_finished_reservation(): void
    {
        $reservation = Reservation::factory()->finished()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/reservations/{$reservation->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_can_generate_quote(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(4)->format('Y-m-d'),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', $data);

        $this->assertApiResponse($response);
        // 4 noches x 100 = 400
        $response->assertJsonPath('data.total', 400.0);
        $response->assertJsonPath('data.deposit', 200.0);
        $response->assertJsonPath('data.balance', 200.0);
        $response->assertJsonPath('data.nights', 4);
        $response->assertJsonPath('data.is_available', true);
    }

    public function test_quote_shows_unavailable_when_cabin_is_booked(): void
    {
        // Crear reserva existente
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(5),
        ]);

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(4)->format('Y-m-d'),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', $data);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.is_available', false);
    }

    public function test_can_filter_reservations_by_status(): void
    {
        Reservation::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reservations?status=confirmed');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(2, 'data');
    }

    public function test_can_filter_reservations_by_date_range_fully_within(): void
    {
        // Crear una reserva del 10 al 15
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::now()->addDays(10),
            'check_out_date' => Carbon::now()->addDays(15),
        ]);

        // Filtrar por rango que envuelve la reserva (5 al 20)
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reservations?start_date=' . Carbon::now()->addDays(5)->format('Y-m-d') . '&end_date=' . Carbon::now()->addDays(20)->format('Y-m-d'));

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_can_filter_reservations_by_date_range_starts_before_ends_within(): void
    {
        // Crear una reserva del 10 al 15
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::now()->addDays(10),
            'check_out_date' => Carbon::now()->addDays(15),
        ]);

        // Filtrar por rango que comienza antes y termina dentro (5 al 12)
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reservations?start_date=' . Carbon::now()->addDays(5)->format('Y-m-d') . '&end_date=' . Carbon::now()->addDays(12)->format('Y-m-d'));

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_can_filter_reservations_by_date_range_starts_within_ends_after(): void
    {
        // Crear una reserva del 10 al 15
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::now()->addDays(10),
            'check_out_date' => Carbon::now()->addDays(15),
        ]);

        // Filtrar por rango que comienza dentro y termina después (12 al 20)
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reservations?start_date=' . Carbon::now()->addDays(12)->format('Y-m-d') . '&end_date=' . Carbon::now()->addDays(20)->format('Y-m-d'));

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $reservation->id);
    }

    public function test_can_filter_reservations_by_date_range_no_overlap(): void
    {
        // Crear una reserva del 10 al 15
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::now()->addDays(10),
            'check_out_date' => Carbon::now()->addDays(15),
        ]);

        // Filtrar por rango sin solapamiento (20 al 25)
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reservations?start_date=' . Carbon::now()->addDays(20)->format('Y-m-d') . '&end_date=' . Carbon::now()->addDays(25)->format('Y-m-d'));

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(0, 'data');
    }

    public function test_can_filter_reservations_by_date_range_with_status_filter(): void
    {
        // Crear reserva confirmada del 10 al 15
        $confirmedReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::now()->addDays(10),
            'check_out_date' => Carbon::now()->addDays(15),
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        // Crear reserva cancelada del 10 al 15
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::now()->addDays(10),
            'check_out_date' => Carbon::now()->addDays(15),
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        // Filtrar por rango + estado confirmado
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/reservations?start_date=' . Carbon::now()->addDays(5)->format('Y-m-d') . '&end_date=' . Carbon::now()->addDays(20)->format('Y-m-d') . '&status=confirmed');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $confirmedReservation->id);
        $response->assertJsonPath('data.0.status', 'confirmed');
    }
}
