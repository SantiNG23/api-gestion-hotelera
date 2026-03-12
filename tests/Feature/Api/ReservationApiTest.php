<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\Client;
use App\Models\PriceGroup;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\ReservationPayment;
use App\Models\Tenant;
use App\Models\User;
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

    public function test_create_reservation_rejects_missing_tariff_configuration(): void
    {
        $cabinWithoutTariff = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $cabinWithoutTariff->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Sin Tarifa',
                'dni' => '56565656',
            ],
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pricing']);
    }

    public function test_update_reservation_rejects_missing_tariff_configuration_using_effective_state(): void
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

        $cabinWithoutTariff = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        $response = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'cabin_id' => $cabinWithoutTariff->id,
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pricing']);
    }

    public function test_update_reservation_rejects_when_effective_guest_count_exceeds_new_cabin_capacity(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'num_guests' => 4,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'cabin_id' => $this->otherCabin->id,
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['num_guests']);
    }

    public function test_create_block_allows_zero_price_without_tariff_configuration(): void
    {
        $cabinWithoutTariff = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $cabinWithoutTariff->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertTrue($response->json('data.is_blocked'));
        $this->assertEquals(0, $response->json('data.total_price'));
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

    // ============= Pruebas Exhaustivas de Bloqueos =============

    public function test_create_multiple_blocks_same_cabin(): void
    {
        $checkIn1 = Carbon::tomorrow();
        $checkOut1 = $checkIn1->clone()->addDays(2);

        // Crear primer bloqueo
        $response1 = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn1->format('Y-m-d'),
            'check_out_date' => $checkOut1->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        $response1->assertStatus(201);

        $checkIn2 = Carbon::tomorrow()->addDays(5);
        $checkOut2 = $checkIn2->clone()->addDays(2);

        // Crear segundo bloqueo
        $response2 = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn2->format('Y-m-d'),
            'check_out_date' => $checkOut2->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        $response2->assertStatus(201);

        // Ambos deben tener precio 0
        $this->assertEquals(0, $response1->json('data.total_price'));
        $this->assertEquals(0, $response2->json('data.total_price'));
    }

    public function test_block_prevents_normal_reservation(): void
    {
        $blockCheckIn = Carbon::tomorrow();
        $blockCheckOut = $blockCheckIn->clone()->addDays(3);

        // Crear bloqueo
        $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $blockCheckIn->format('Y-m-d'),
            'check_out_date' => $blockCheckOut->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        // Intentar crear en fechas bloqueadas
        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $blockCheckIn->addDays(1)->format('Y-m-d'),
            'check_out_date' => $blockCheckOut->addDays(1)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Blocked User',
                'dni' => '88888888',
            ],
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_blocks_dont_affect_other_cabins(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        // Bloquear cabaña principal
        $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        // Cabaña alternativa debería estar disponible
        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->otherCabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Other Cabin User',
                'dni' => '99999999',
            ],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertFalse($response->json('data.is_blocked'));
        $this->assertGreaterThan(0, $response->json('data.total_price'));
    }

    public function test_convert_reservation_to_block(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'num_guests' => 5,
            'is_blocked' => false,
            'total_price' => 300,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        // Convertir a bloqueo
        $response = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'is_blocked' => true,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.is_blocked'));
        $this->assertEquals(0, $response->json('data.total_price'));
        $this->assertEquals(Client::DNI_BLOCK, $response->json('data.client.dni'));
        $this->assertNull($response->json('data.pending_until'));
    }

    public function test_convert_block_to_normal_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'is_blocked' => true,
            'total_price' => 0,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'num_guests' => 2,
        ]);

        // Convertir a reserva normal
        $response = $this->putJson("/api/v1/reservations/{$reservation->id}", [
            'is_blocked' => false,
            'client' => [
                'name' => 'Real Reservation',
                'dni' => '11111112',
            ],
            'num_guests' => 2,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.is_blocked'));
        $this->assertGreaterThan(0, $response->json('data.total_price'));
        $this->assertNotEquals(Client::DNI_BLOCK, $response->json('data.client.dni'));
        $this->assertNotNull($response->json('data.pending_until'));
    }

    public function test_create_reservation_rejects_cabin_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCabin = Cabin::factory()->create([
            'tenant_id' => $otherTenant->id,
            'capacity' => 4,
        ]);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $foreignCabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Cross Tenant',
                'dni' => '12121212',
            ],
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cabin_id']);
    }

    public function test_block_has_no_pending_hours(): void
    {
        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertNull($response->json('data.pending_until'));
    }

    public function test_block_uses_special_client(): void
    {
        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertEquals(Client::DNI_BLOCK, $response->json('data.client.dni'));
        $this->assertStringContainsString('BLOQUEO', $response->json('data.client.name'));
    }

    public function test_blocked_reservation_can_be_confirmed_with_zero_payment(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'is_blocked' => true,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'deposit_amount' => 0,
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->id}/confirm", [], $this->authHeaders());

        // Una reserva bloqueada con deposit_amount=0 puede confirmarse (pago de 0)
        $response->assertStatus(200);
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $response->json('data.status'));
    }

    public function test_create_reservation_with_soft_deleted_client_dni(): void
    {
        $this->markTestIncomplete('Pendiente de definición funcional sobre reutilización de DNI con soft delete; fuera de esta tanda de trabajo.');

        // TODO: Modificar este test cuando se defina la respuesta esperada
        // Opciones bajo consideración:
        // 1. Restaurar automáticamente el cliente y permitir crear la reserva
        // 2. Validar y rechazar con error 422 clara
        // 3. Permitir reutilización de DNI en clientes eliminados mediante política de base de datos

        // 1. Crear un cliente
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dni' => '99999999',
            'name' => 'Client to Delete',
        ]);

        // 2. Eliminarlo (soft delete)
        $client->delete();

        // 3. Intentar crear una reserva con el mismo DNI
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(2);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Client to Delete',
                'dni' => '99999999', // DNI del cliente eliminado
                'email' => 'client@example.com',
            ],
        ], $this->authHeaders());

        // Comportamiento actual: Error 500 de integridad de BD
        // SQLSTATE[23000]: Unique constraint failed
        dump('Response status: '.$response->status());
        dump('Response body:', $response->json());
    }

    // ============= TESTS PARA VALIDAR RIESGOS CRÍTICOS =============

    /**
     * TEST RIESGO 12 CRÍTICA: ReservationPayment sin scope de tenant permite data leakage
     * Intenta acceder a ReservationPayment de otro tenant
     */
    public function test_reservation_payment_isolation_between_tenants(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #12 CRÍTICA - ReservationPayment sin scope de tenant permite data leakage multitenant');

        // Setup: crear 2 tenants con usuarios y reservas
        $tenant1 = $this->tenant; // Tenant actual (usuario autenticado)
        $tenant2 = Tenant::factory()->create();

        // Usuario en tenant 2
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // Cabaña y cliente en tenant 2
        $cabin2 = Cabin::factory()->create(['tenant_id' => $tenant2->id, 'capacity' => 4]);
        $client2 = Client::factory()->create(['tenant_id' => $tenant2->id]);

        // Configurar precios para tenant 2
        $pg2 = PriceGroup::factory()->create(['tenant_id' => $tenant2->id, 'is_default' => true]);
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $tenant2->id,
            'cabin_id' => $cabin2->id,
            'price_group_id' => $pg2->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        // Crear reserva en tenant 2
        $reservation2 = Reservation::factory()->create([
            'tenant_id' => $tenant2->id,
            'cabin_id' => $cabin2->id,
            'client_id' => $client2->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        // Crear pago en tenant 2
        $payment2 = ReservationPayment::factory()->create([
            'tenant_id' => $tenant2->id,
            'reservation_id' => $reservation2->id,
            'payment_type' => 'deposit',
            'amount' => 500,
        ]);

        // INTENTO 1: Acceso directo por ID (sin pasar por Reservation)
        // Si ReservationPayment no tiene scope BelongsToTenant, puedo obtenerlo
        $directAccess = ReservationPayment::find($payment2->id);

        $this->assertNull(
            $directAccess,
            'BUG CONFIRMADO: ReservationPayment::find() sin scope permite acceso a datos de otro tenant'
        );

        // INTENTO 2: Acceso por rango de IDs secuenciales
        // Si alguien por error/adivinanza intenta: GET /api/payments?id[gte]=1
        $rangeAccess = ReservationPayment::where('id', '>', 0)->get();
        $payment2InRange = $rangeAccess->contains('id', $payment2->id);

        $this->assertFalse(
            $payment2InRange,
            'BUG CONFIRMADO: ReservationPayment sin scope permite lectura de rango transversal entre tenants'
        );
    }

    /**
     * TEST RIESGO 12 CRÍTICA: ReservationGuest sin scope de tenant permite data leakage
     * Intenta acceder a ReservationGuest de otro tenant
     */
    public function test_reservation_guest_isolation_between_tenants(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #12 CRÍTICA - ReservationGuest sin scope de tenant permite data leakage multitenant');

        // Setup duplicado: 2 tenants, usuario en tenant 2
        $tenant1 = $this->tenant;
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // Cabaña y cliente en tenant 2
        $cabin2 = Cabin::factory()->create(['tenant_id' => $tenant2->id, 'capacity' => 6]);
        $client2 = Client::factory()->create(['tenant_id' => $tenant2->id]);

        // Configurar precios para tenant 2
        $pg2 = PriceGroup::factory()->create(['tenant_id' => $tenant2->id, 'is_default' => true]);
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $tenant2->id,
            'cabin_id' => $cabin2->id,
            'price_group_id' => $pg2->id,
            'num_guests' => 3,
            'price_per_night' => 100,
        ]);

        // Crear reserva con huéspedes en tenant 2
        $reservation2 = Reservation::factory()->create([
            'tenant_id' => $tenant2->id,
            'cabin_id' => $cabin2->id,
            'client_id' => $client2->id,
            'num_guests' => 3,
        ]);

        // Crear huéspedes en tenant 2
        $guest2_1 = $reservation2->guests()->create([
            'name' => 'Guest Sensitive T2',
            'dni' => '11111111',
            'email' => 'guest.t2@example.com',
            'phone' => '5555555',
        ]);

        $guest2_2 = $reservation2->guests()->create([
            'name' => 'Minor T2',
            'dni' => '22222222',
            'email' => 'minor.t2@example.com',
            'phone' => '5555556',
        ]);

        // INTENTO 1: Acceso directo por ID
        $directAccess = ReservationGuest::find($guest2_1->id);

        $this->assertNull(
            $directAccess,
            'BUG CONFIRMADO: ReservationGuest::find() sin scope permite acceso a datos sensibles de otro tenant'
        );

        // INTENTO 2: Acceso a todos los huéspedes sin scope
        $allGuests = ReservationGuest::all();
        $guestsFromT2 = $allGuests->whereIn('id', [$guest2_1->id, $guest2_2->id]);

        $this->assertCount(
            0,
            $guestsFromT2,
            'BUG CONFIRMADO: ReservationGuest::all() sin scope expone todos los huéspedes de todos los tenants'
        );

        // INTENTO 3: Filtrar por DNI (adivinanza)
        $dniLookup = ReservationGuest::where('dni', '11111111')->first();

        $this->assertNull(
            $dniLookup,
            'SEGURIDAD CRÍTICA: ReservationGuest permite búsqueda por DNI sin validación de tenant'
        );
    }

    /**
     * TEST RIESGO 14 CRÍTICA: tenant_id recibido en createReservation() no se valida contra usuario autenticado
     * Intenta crear una reserva con tenant_id diferente al del usuario autenticado
     */
    public function test_create_reservation_tenant_id_not_validated(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #14 CRÍTICA - tenant_id recibido en payload no se valida contra usuario autenticado');

        // Setup: crear un segundo tenant "atacante"
        $tenant1 = $this->tenant; // Usuario está autenticado aquí
        $tenant2 = Tenant::factory()->create();

        // Cabaña en tenant 2 (no debería ser accesible para usuario de tenant 1)
        $cabin2 = Cabin::factory()->create(['tenant_id' => $tenant2->id, 'capacity' => 4]);

        // Configurar precios en tenant 2
        $pg2 = PriceGroup::factory()->create(['tenant_id' => $tenant2->id, 'is_default' => true]);
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $tenant2->id,
            'cabin_id' => $cabin2->id,
            'price_group_id' => $pg2->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        // ATAQUE: Usuario autenticado en tenant 1 intenta crear reserva especificando tenant_id = 2
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(2);

        $response = $this->postJson('/api/v1/reservations', [
            'tenant_id' => $tenant2->id,  // ← INTENTO DE TENANCY BYPASS
            'cabin_id' => $cabin2->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Attacker Client',
                'dni' => '99988877',
                'email' => 'attacker@example.com',
            ],
        ], $this->authHeaders()); // Headers de tenant 1, pero payload pide tenant 2

        // Debería ser rechazado con 422 o equivalente
        if ($response->getStatusCode() === 201) {
            // Bug confirmado: la reserva se creó en tenant 2
            $createdReservation = Reservation::find($response->json('data.id'));

            $this->assertEquals(
                $tenant2->id,
                $createdReservation->tenant_id,
                'BUG CONFIRMADO: Usuario autenticado en tenant 1 creó reserva en tenant 2 especificando tenant_id en payload'
            );
        }
    }

    /**
     * TEST RIESGO 11: Relaciones BelongsTo sin withTrashed() devuelven null si entidad relacionada fue soft-deleted
     * Si un Client es eliminado (soft delete), $reservation->client devuelve null
     */
    public function test_reservation_client_relation_returns_null_when_client_soft_deleted(): void
    {
        // Crear una reserva con cliente
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(2);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Client To Delete',
                'dni' => '55556666',
                'email' => 'delete.client@example.com',
            ],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $reservationId = $response->json('data.id');
        $clientId = $response->json('data.client.id');

        // Cargar la reserva y verificar que tiene cliente
        $reservation = Reservation::find($reservationId);
        $this->assertNotNull($reservation->client, 'Cliente debe estar disponible inicialmente');

        // Soft delete el cliente
        Client::find($clientId)->delete();

        // Recargar la reserva en API: debe conservar el cliente histórico para trazabilidad
        $reservationAfterDelete = Reservation::find($reservationId);

        $showResponse = $this->getJson("/api/v1/reservations/{$reservationId}", $this->authHeaders());
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.client.id', $clientId);
        $showResponse->assertJsonPath('data.client.name', 'Client To Delete');

        // La relación base sigue en null (solo activos)
        $this->assertNull(
            $reservationAfterDelete->client,
            'La relación base client() no debe traer soft-deleted automáticamente'
        );

        // La relación explícita histórica sí debe recuperarlo
        $clientViaWithTrashed = $reservationAfterDelete->clientWithTrashed()->first();
        $this->assertNotNull(
            $clientViaWithTrashed,
            'La relación histórica debe recuperar el cliente eliminado lógicamente'
        );
    }

    /**
     * TEST RIESGO 13: Manejo de errores no uniforme en ReservationController
     * Diferentes endpoints devuelven formatos de error inconsistentes
     */
    public function test_error_handling_inconsistency_across_endpoints(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #13 - Manejo de errores no uniforme en diferentes endpoints');

        // Intento 1: Buscar reserva inexistente (ModelNotFoundException)
        $invalidId = 999999;
        $response1 = $this->getJson("/api/v1/reservations/{$invalidId}", $this->authHeaders());

        // Intento 2: Confirmar reserva sin payload (ValidationException)
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $response2 = $this->postJson("/api/v1/reservations/{$reservation->id}/confirm", [], $this->authHeaders());

        // Si no hay manejo uniforme, algunos endpoints devolverán diferentes estructuras
        // Endpoint 1: puede devolver error 404 global
        // Endpoint 2: puede devolver validación personalizada

        // El bug es que no hay un `try/catch` uniforme que asegure formato consistente
        $response1Status = $response1->status();
        $response2Status = $response2->status();

        // Ambos son errores, pero formateo podría ser diferente
        $this->assertTrue(
            in_array($response1Status, [400, 404, 422]),
            'Response 1 (show inexistente) debe ser error pero formato depende de handler global'
        );

        $this->assertTrue(
            in_array($response2Status, [400, 422]),
            'Response 2 (validación) debe ser 422 pero estructura depende de cada endpoint'
        );

        // Sin controlador central de errores, los formatos JSON pueden diferir:
        // response1 puede tener: { "message": "...", "exception": "..." }
        // response2 puede tener: { "success": false, "errors": {...} }
    }

    /**
     * TEST RIESGO 15: syncGuests() elimina huéspedes con eliminación física sin auditoría ni soft delete
     * Cuando se actualiza una reserva, huéspedes anteriores se borran definitivamente
     */
    public function test_sync_guests_deletes_permanently_without_audit(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #15 - syncGuests() elimina huéspedes con eliminación física sin auditoría');

        // Crear reserva con huéspedes iniciales
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        $response = $this->postJson('/api/v1/reservations', [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 3,
            'client' => [
                'name' => 'Client With Guests',
                'dni' => '33334444',
                'email' => 'family@example.com',
            ],
            'guests' => [
                ['name' => 'Guest 1', 'dni' => '44445555'],
                ['name' => 'Guest 2', 'dni' => '55556666'],
                ['name' => 'Guest 3', 'dni' => '66667777'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $reservationId = $response->json('data.id');

        // Verificar que se crearon 3 huéspedes
        $reservation = Reservation::find($reservationId);
        $guestCountBefore = $reservation->guests()->count();
        $this->assertEquals(3, $guestCountBefore, 'Deben crearse 3 huéspedes iniciales');

        // Obtener IDs de huéspedes originales para auditoría
        $originalGuestIds = $reservation->guests()->pluck('id')->toArray();

        // Actualizar reserva con nuevos huéspedes
        $updateResponse = $this->patchJson("/api/v1/reservations/{$reservationId}", [
            'num_guests' => 2,
            'guests' => [
                ['name' => 'New Guest 1', 'dni' => '77778888'],
                ['name' => 'New Guest 2', 'dni' => '88889999'],
            ],
        ], $this->authHeaders());

        $updateResponse->assertStatus(200);

        // Verificar que se borraron los anteriores
        $reservation->refresh();
        $guestCountAfter = $reservation->guests()->count();
        $this->assertEquals(2, $guestCountAfter, 'guest count después debe ser 2');

        // LO CRÍTICO: los huéspedes anteriores fueron ELIMINADOS FÍSICAMENTE
        // No hay soft delete, no hay historial, no hay auditoría
        $guestsStillExist = ReservationGuest::whereIn('id', $originalGuestIds)->count();

        $this->assertEquals(
            0,
            $guestsStillExist,
            'BUG CONFIRMADO: Huéspedes originales fueron eliminados FÍSICAMENTE sin auditoría'
        );

        // Propuesta: usar soft delete
        // $this->assertTrue(
        //     ReservationGuest::onlyTrashed()->whereIn('id', $originalGuestIds)->exists(),
        //     'CON FIX: Huéspedes eliminados deberían existir en soft delete'
        // );
    }
}
