<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\Client;
use App\Models\PriceGroup;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use Carbon\Carbon;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    private Cabin $cabin;
    private Client $client;
    private PriceGroup $priceGroup;
    private Cabin $otherCabin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();

        // Crear cabaña principal
        $this->cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        // Crear cabaña alternativa
        $this->otherCabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 2,
        ]);

        // Crear grupo de precio por defecto
        $this->priceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_per_night' => 100,
            'is_default' => true,
        ]);

        // Configurar precios por huéspedes
        foreach ([2, 3, 4] as $guests) {
            CabinPriceByGuests::factory()->create([
                'tenant_id' => $this->tenant->id,
                'cabin_id' => $this->cabin->id,
                'price_group_id' => $this->priceGroup->id,
                'num_guests' => $guests,
                'price_per_night' => 100,
            ]);

            CabinPriceByGuests::factory()->create([
                'tenant_id' => $this->tenant->id,
                'cabin_id' => $this->otherCabin->id,
                'price_group_id' => $this->priceGroup->id,
                'num_guests' => $guests,
                'price_per_night' => 80,
            ]);
        }

        // Crear cliente de prueba
        $this->client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'dni' => '12345678',
        ]);
    }

    // ============= Flujo Completo - 4 tests =============

    public function test_full_reservation_flow_create_confirm_checkin_checkout(): void
    {
        // 1. Crear reserva
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        $createResponse = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Jane Doe',
                'dni' => '87654321',
                'email' => 'jane@example.com',
            ],
        ], $this->authHeaders());

        $createResponse->assertStatus(201);
        $reservationId = $createResponse->json('data.id');
        $this->assertEquals(Reservation::STATUS_PENDING_CONFIRMATION, $createResponse->json('data.status'));

        // 2. Confirmar reserva
        $confirmResponse = $this->postJson("/api/v1/reservations/{$reservationId}/confirm", [
            'payment_method' => 'credit_card',
        ], $this->authHeaders());

        $confirmResponse->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $confirmResponse->json('data.status'));

        // 3. Pagar saldo anticipadamente
        $payResponse = $this->postJson("/api/v1/reservations/{$reservationId}/pay-balance", [
            'payment_method' => 'bank_transfer',
        ], $this->authHeaders());

        $payResponse->assertStatus(200);

        // 4. Check-in
        $checkinResponse = $this->postJson("/api/v1/reservations/{$reservationId}/check-in", [
            'payment_method' => 'cash',
        ], $this->authHeaders());

        $checkinResponse->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CHECKED_IN, $checkinResponse->json('data.status'));

        // 5. Check-out
        $checkoutResponse = $this->postJson("/api/v1/reservations/{$reservationId}/check-out", [], $this->authHeaders());

        $checkoutResponse->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_FINISHED, $checkoutResponse->json('data.status'));
    }

    public function test_full_reservation_flow_with_anticipado_payment(): void
    {
        $checkIn = Carbon::tomorrow()->addDays(5);
        $checkOut = $checkIn->clone()->addDays(3);

        // Crear reserva
        $createResponse = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Anticipado User',
                'dni' => '99999999',
            ],
        ], $this->authHeaders());

        $reservationId = $createResponse->json('data.id');

        // Confirmar
        $this->postJson("/api/v1/reservations/{$reservationId}/confirm", [], $this->authHeaders());

        // Pagar saldo anticipadamente
        $this->postJson("/api/v1/reservations/{$reservationId}/pay-balance", [], $this->authHeaders());

        // Check-in sin pago adicional
        $checkinResponse = $this->postJson("/api/v1/reservations/{$reservationId}/check-in", [], $this->authHeaders());

        $this->assertEquals(Reservation::STATUS_CHECKED_IN, $checkinResponse->json('data.status'));

        // Verificar que ambos pagos están registrados
        $showResponse = $this->getJson("/api/v1/reservations/{$reservationId}", $this->authHeaders());
        $this->assertCount(2, $showResponse->json('data.payments'));
    }

    public function test_full_reservation_flow_with_guests(): void
    {
        $checkIn = Carbon::tomorrow()->addDays(10);
        $checkOut = $checkIn->clone()->addDays(2);

        $createResponse = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 3,
            'client' => [
                'name' => 'Family Head',
                'dni' => '55555555',
            ],
            'guests' => [
                ['name' => 'Guest 1', 'dni' => 'G1', 'age' => 25],
                ['name' => 'Guest 2', 'dni' => 'G2', 'age' => 30],
            ],
        ], $this->authHeaders());

        $createResponse->assertStatus(201);
        $this->assertCount(2, $createResponse->json('data.guests'));
    }

    public function test_cannot_modify_after_checkout(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_FINISHED,
        ]);

        $updateResponse = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'notes' => 'Should fail',
        ], $this->authHeaders());

        $updateResponse->assertStatus(422);
    }

    // ============= Creación - 4 tests =============

    public function test_create_reservation_basic_success(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Test User',
                'dni' => '11111111',
                'email' => 'test@example.com',
            ],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'cabin_id',
                'check_in_date',
                'check_out_date',
                'status',
                'total_price',
                'deposit_amount',
                'balance_amount',
            ],
        ]);

        $this->assertEquals(300, $response->json('data.total_price')); // 3 noches x 100
    }

    public function test_create_reservation_calculates_price(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Price Test',
                'dni' => '22222222',
            ],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertEquals(300, $response->json('data.total_price'));
        $this->assertEquals(150, $response->json('data.deposit_amount')); // 50%
        $this->assertEquals(150, $response->json('data.balance_amount')); // 50%
    }

    public function test_create_reservation_with_block(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertTrue($response->json('data.is_blocked'));
        $this->assertEquals(0, $response->json('data.total_price'));
        $this->assertEquals(Client::DNI_BLOCK, $response->json('data.client.dni'));
    }

    public function test_create_reservation_unavailable_dates(): void
    {
        // Crear una reserva bloqueante
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        // Intentar crear en fechas ocupadas
        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(4)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Overlap Test',
                'dni' => '33333333',
            ],
        ], $this->authHeaders());

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => ['cabin_id'],
        ]);
    }

    // ============= Actualización - 3 tests =============

    public function test_update_reservation_notes_only(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'total_price' => 300,
        ]);

        $response = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'notes' => 'Updated notes via API',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals('Updated notes via API', $response->json('data.notes'));
        $this->assertEquals(300, $response->json('data.total_price'));
    }

    public function test_update_reservation_dates_recalculates(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'num_guests' => 2,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $newCheckIn = Carbon::tomorrow()->addDays(10);
        $newCheckOut = $newCheckIn->clone()->addDays(4);

        $response = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'check_in_date' => $newCheckIn->format('Y-m-d'),
            'check_out_date' => $newCheckOut->format('Y-m-d'),
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(400, $response->json('data.total_price')); // 4 noches x 100
    }

    public function test_update_reservation_invalid_state(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_FINISHED,
        ]);

        $response = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'notes' => 'Try update',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    // ============= Confirmación - 2 tests =============

    public function test_confirm_reservation_success(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'deposit_amount' => 150,
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/confirm", [
            'payment_method' => 'credit_card',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $response->json('data.status'));
        $this->assertNull($response->json('data.pending_until'));
    }

    public function test_confirm_already_confirmed(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        // Crear pago para simular confirmación previa
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->deposit_amount,
            'payment_type' => ReservationPayment::TYPE_DEPOSIT,
            'paid_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/confirm", [], $this->authHeaders());

        $response->assertStatus(422);
    }

    // ============= Pagos - 2 tests =============

    public function test_pay_balance_anticipado(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'balance_amount' => 150,
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/pay-balance", [
            'payment_method' => 'bank_transfer',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $response->json('data.status'));
    }

    public function test_pay_balance_already_paid(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'balance_amount' => 150,
        ]);

        // Pago ya realizado
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 150,
            'payment_type' => ReservationPayment::TYPE_BALANCE,
            'paid_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/pay-balance", [], $this->authHeaders());

        $response->assertStatus(422);
    }

    // ============= Check-In - 2 tests =============

    public function test_check_in_with_pending_balance(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'balance_amount' => 150,
        ]);

        // Pago de seña (confirmación)
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->deposit_amount,
            'payment_type' => ReservationPayment::TYPE_DEPOSIT,
            'paid_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/check-in", [
            'payment_method' => 'cash',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CHECKED_IN, $response->json('data.status'));
    }

    public function test_check_in_with_anticipado_balance(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'balance_amount' => 150,
        ]);

        // Ambos pagos ya realizados
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->deposit_amount,
            'payment_type' => ReservationPayment::TYPE_DEPOSIT,
            'paid_at' => now(),
        ]);

        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 150,
            'payment_type' => ReservationPayment::TYPE_BALANCE,
            'paid_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/check-in", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CHECKED_IN, $response->json('data.status'));
    }

    // ============= Check-Out - 1 test =============

    public function test_check_out_success(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CHECKED_IN,
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/check-out", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_FINISHED, $response->json('data.status'));
    }

    // ============= Cancel - 1 test =============

    public function test_cancel_reservation_success(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response = $this->deleteJson("/api/v1/reservations/{$reservation->id}", [], $this->authHeaders());

        $response->assertStatus(200);
    }
}
