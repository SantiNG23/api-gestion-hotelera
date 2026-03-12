<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\ReservationCreated;
use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\Client;
use App\Models\PriceGroup;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\Tenant;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    private ReservationService $service;
    protected ?Cabin $cabin = null;
    protected ?Cabin $otherCabin = null;
    protected ?Client $client = null;
    protected ?PriceGroup $priceGroup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $auth = $this->createAuthenticatedUser();
        $this->actingAs($auth['user']);

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
        }

        // Precios para cabaña alternativa
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->otherCabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 80,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->otherCabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 3,
            'price_per_night' => 80,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->otherCabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 4,
            'price_per_night' => 80,
        ]);

        // Crear cliente de prueba
        $this->client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'dni' => '12345678',
        ]);

        $this->service = app(ReservationService::class);
    }

    // ============= getReservations() - 3 tests =============

    public function test_get_reservations_returns_paginated_list(): void
    {
        // Crear 3 reservas
        $reservations = Reservation::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
        ]);

        $result = $this->service->getReservations([
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'id',
            'sort_order' => 'asc',
            'filters' => [],
            'date_range' => ['start' => null, 'end' => null],
        ]);

        $this->assertCount(3, $result->items());
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(10, $result->perPage());
    }

    public function test_get_reservations_filters_by_status(): void
    {
        // Crear reservas con diferentes estados (asegurar mismo tenant)
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $result = $this->service->getReservations([
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'id',
            'sort_order' => 'asc',
            'filters' => ['status' => Reservation::STATUS_CONFIRMED],
            'date_range' => ['start' => null, 'end' => null],
        ]);

        $this->assertCount(1, $result->items());
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $result->items()[0]->status);
    }

    public function test_get_reservations_filters_by_date_range(): void
    {
        // Crear reservas en diferentes fechas
        $checkInDate = Carbon::now()->addDays(5);
        $checkOutDate = $checkInDate->clone()->addDays(3);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
        ]);

        // Reserva fuera del rango
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'check_in_date' => Carbon::now()->addMonths(2),
            'check_out_date' => Carbon::now()->addMonths(2)->addDays(3),
        ]);

        $result = $this->service->getReservations([
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'id',
            'sort_order' => 'asc',
            'filters' => [],
            'date_range' => [
                'start' => $checkInDate->format('Y-m-d'),
                'end' => $checkInDate->addDays(2)->format('Y-m-d'),
            ],
        ]);

        $this->assertCount(1, $result->items());
    }

    // ============= getReservation() - 1 test =============

    public function test_get_reservation_with_relations(): void
    {
        // Crear reserva con relaciones
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
        ]);

        ReservationPayment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->deposit_amount,
            'payment_type' => ReservationPayment::TYPE_DEPOSIT,
        ]);

        $result = $this->service->getReservation($reservation->id);

        $this->assertEquals($reservation->id, $result->id);
        $this->assertNotNull($result->client);
        $this->assertNotNull($result->cabin);
        $this->assertCount(1, $result->payments);
    }

    // ============= createReservation() - 7 tests =============

    public function test_create_reservation_basic(): void
    {
        Event::fake();

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Jane Doe',
                'dni' => '87654321',
            ],
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertEquals(Reservation::STATUS_PENDING_CONFIRMATION, $reservation->status);
        $this->assertEquals(300, $reservation->total_price); // 3 noches x 100
        $this->assertEquals(150, $reservation->deposit_amount); // 50%
        $this->assertEquals(150, $reservation->balance_amount); // 50%
        $this->assertFalse($reservation->is_blocked);
        Event::assertDispatched(ReservationCreated::class, function (ReservationCreated $event): bool {
            return $event->tenantId === $this->tenant->id
                && $event->reservationId > 0;
        });
    }

    public function test_create_reservation_with_guests(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 3,
            'client' => [
                'name' => 'John Smith',
                'dni' => '11111111',
            ],
            'guests' => [
                ['name' => 'Guest 1', 'dni' => 'G1', 'age' => 30],
                ['name' => 'Guest 2', 'dni' => 'G2', 'age' => 28],
            ],
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertCount(2, $reservation->guests);
        $this->assertEquals('Guest 1', $reservation->guests[0]->name);
    }

    public function test_create_reservation_blocked(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'is_blocked' => true,
            'client' => [
                'name' => 'BLOQUEO DE FECHAS',
                'dni' => Client::DNI_BLOCK,
            ],
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertEquals(0, $reservation->total_price);
        $this->assertEquals(0, $reservation->deposit_amount);
        $this->assertEquals(0, $reservation->balance_amount);
        $this->assertTrue($reservation->is_blocked);
        $this->assertEquals(Client::DNI_BLOCK, $reservation->client->dni);
    }

    public function test_create_reservation_recalculates_price(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(5);

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Test User',
                'dni' => '99999999',
            ],
        ];

        $reservation = $this->service->createReservation($data);

        // 5 noches x 100 = 500
        $this->assertEquals(500, $reservation->total_price);
        $this->assertEquals(250, $reservation->deposit_amount);
        $this->assertEquals(250, $reservation->balance_amount);
    }

    public function test_create_reservation_validates_availability(): void
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

        // Intentar crear reserva en fechas ocupadas
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(4)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Overlapping',
                'dni' => '55555555',
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->service->createReservation($data);
    }

    public function test_create_reservation_dispatches_event(): void
    {
        Event::fake();

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Event Test',
                'dni' => '77777777',
            ],
        ];

        $this->service->createReservation($data);

        Event::assertDispatched(ReservationCreated::class, function (ReservationCreated $event): bool {
            return $event->tenantId === $this->tenant->id
                && $event->reservationId > 0;
        });
    }

    public function test_create_reservation_without_required_dni(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'No DNI',
                // Falta el DNI
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->service->createReservation($data);
    }

    public function test_create_reservation_rejects_payload_tenant_override(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('tenant_id');

        $this->service->createReservation([
            'tenant_id' => $otherTenant->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Payload Override',
                'dni' => '67676767',
            ],
        ]);
    }

    // ============= updateReservation() - 5 tests =============

    public function test_update_reservation_notes(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'total_price' => 300,
        ]);

        $originalPrice = $reservation->total_price;

        $updated = $this->service->updateReservation($reservation->id, [
            'notes' => 'Updated notes',
        ]);

        $this->assertEquals('Updated notes', $updated->notes);
        $this->assertEquals($originalPrice, $updated->total_price);
    }

    public function test_update_reservation_dates_recalculates_price(): void
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
        $newCheckOut = $newCheckIn->clone()->addDays(4); // 4 noches

        $updated = $this->service->updateReservation($reservation->id, [
            'check_in_date' => $newCheckIn->format('Y-m-d'),
            'check_out_date' => $newCheckOut->format('Y-m-d'),
        ]);

        // 4 noches x 100 = 400
        $this->assertEquals(400, $updated->total_price);
        $this->assertEquals($newCheckIn->format('Y-m-d'), Carbon::parse($updated->check_in_date)->format('Y-m-d'));
    }

    public function test_update_reservation_cabin_recalculates_price(): void
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

        $updated = $this->service->updateReservation($reservation->id, [
            'cabin_id' => $this->otherCabin->id,
        ]);

        // Cambio de cabaña, precio diferente (3 noches x 80 = 240)
        $this->assertEquals($this->otherCabin->id, $updated->cabin_id);
        $this->assertEquals(240, $updated->total_price);
    }

    public function test_update_reservation_uses_effective_state_for_capacity_validation(): void
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

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('capacidad para 2 personas');

        $this->service->updateReservation($reservation->id, [
            'cabin_id' => $this->otherCabin->id,
        ]);
    }

    public function test_update_reservation_rejects_missing_tariff_configuration_for_normal_reservation(): void
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

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No hay configuración tarifaria');

        $this->service->updateReservation($reservation->id, [
            'cabin_id' => $cabinWithoutTariff->id,
        ]);
    }

    public function test_update_reservation_cannot_modify_finished(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_FINISHED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updateReservation($reservation->id, [
            'notes' => 'Should fail',
        ]);
    }

    public function test_update_reservation_cannot_modify_cancelled(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updateReservation($reservation->id, [
            'notes' => 'Should fail',
        ]);
    }

    public function test_update_reservation_rejects_payload_tenant_override(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $otherTenant = Tenant::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('tenant_id');

        $this->service->updateReservation($reservation->id, [
            'tenant_id' => $otherTenant->id,
            'notes' => 'Intento de override',
        ]);
    }

    // ============= confirm() - 3 tests =============

    public function test_confirm_reservation_creates_payment(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'deposit_amount' => 150,
        ]);

        $confirmed = $this->service->confirm($reservation->id, [
            'payment_method' => 'credit_card',
        ]);

        $this->assertEquals(Reservation::STATUS_CONFIRMED, $confirmed->status);
        $this->assertNull($confirmed->pending_until);
        $this->assertCount(1, $confirmed->payments);
        $this->assertEquals(150, $confirmed->payments[0]->amount);
        $this->assertEquals(ReservationPayment::TYPE_DEPOSIT, $confirmed->payments[0]->payment_type);
    }

    public function test_confirm_reservation_only_pending(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->confirm($reservation->id, []);
    }

    public function test_confirm_reservation_cannot_pay_deposit_twice(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'deposit_amount' => 150,
        ]);

        // Primera confirmación
        $this->service->confirm($reservation->id, []);

        // Intenta confirmar nuevamente
        $reservation->refresh();
        $this->expectException(ValidationException::class);
        $this->service->confirm($reservation->id, []);
    }

    // ============= payBalance() - 2 tests =============

    public function test_pay_balance_creates_payment(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'balance_amount' => 150,
        ]);

        $updated = $this->service->payBalance($reservation->id, [
            'payment_method' => 'bank_transfer',
        ]);

        $this->assertEquals(Reservation::STATUS_CONFIRMED, $updated->status);
        $this->assertCount(1, $updated->payments);
        $this->assertEquals(150, $updated->payments[0]->amount);
        $this->assertEquals(ReservationPayment::TYPE_BALANCE, $updated->payments[0]->payment_type);
    }

    public function test_pay_balance_only_confirmed(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->payBalance($reservation->id, []);
    }

    // ============= checkIn() - 2 tests =============

    public function test_check_in_with_balance_already_paid(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'balance_amount' => 150,
        ]);

        // Balance ya pagado
        ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => 150,
            'payment_type' => ReservationPayment::TYPE_BALANCE,
            'paid_at' => now(),
        ]);

        $checkedIn = $this->service->checkIn($reservation->id, []);

        $this->assertEquals(Reservation::STATUS_CHECKED_IN, $checkedIn->status);
        $this->assertCount(1, $checkedIn->payments);
    }

    public function test_check_in_pays_balance_on_arrival(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'balance_amount' => 150,
        ]);

        $checkedIn = $this->service->checkIn($reservation->id, [
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(Reservation::STATUS_CHECKED_IN, $checkedIn->status);
        $this->assertCount(1, $checkedIn->payments);
        $this->assertEquals(ReservationPayment::TYPE_BALANCE, $checkedIn->payments[0]->payment_type);
    }

    // ============= checkOut() - 1 test =============

    public function test_check_out_finalizes_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_CHECKED_IN,
        ]);

        $finished = $this->service->checkOut($reservation->id);

        $this->assertEquals(Reservation::STATUS_FINISHED, $finished->status);
    }

    // ============= cancel() - 2 tests =============

    public function test_cancel_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $cancelled = $this->service->cancel($reservation->id);

        $this->assertEquals(Reservation::STATUS_CANCELLED, $cancelled->status);
        $this->assertNull($cancelled->pending_until);
    }

    public function test_cancel_cannot_cancel_finished(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'status' => Reservation::STATUS_FINISHED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->cancel($reservation->id);
    }

    // ============= resolveClient() - 2 tests =============

    public function test_resolve_client_existing(): void
    {
        $existingClient = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
            'dni' => '12121212',
        ]);

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Updated Name',
                'dni' => '12121212',
                'email' => 'new@example.com',
            ],
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertEquals($existingClient->id, $reservation->client_id);
        $this->assertEquals('Updated Name', $reservation->client->name);
        $this->assertEquals('new@example.com', $reservation->client->email);
    }

    public function test_resolve_client_creates_new(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'New Client',
                'dni' => '33333333',
                'email' => 'newclient@example.com',
            ],
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertEquals('New Client', $reservation->client->name);
        $this->assertEquals('33333333', $reservation->client->dni);
        $this->assertEquals('newclient@example.com', $reservation->client->email);
    }

    // ============= Pruebas de Bloqueos (Blocking) =============

    public function test_create_blocked_reservation_has_zero_price(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(5)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertTrue($reservation->is_blocked);
        $this->assertEquals(0, $reservation->total_price);
        $this->assertEquals(0, $reservation->deposit_amount);
        $this->assertEquals(0, $reservation->balance_amount);
    }

    public function test_create_blocked_reservation_uses_block_client(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertEquals(Client::DNI_BLOCK, $reservation->client->dni);
        $this->assertStringContainsString('BLOQUEO', $reservation->client->name);
    }

    public function test_blocked_reservation_blocks_availability(): void
    {
        // Crear bloqueo
        $blockReservation = $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ]);

        // Intentar crear en fechas bloqueadas
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Test User',
                'dni' => '44444444',
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->service->createReservation($data);
    }

    public function test_convert_regular_to_blocked_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'client_id' => $this->client->id,
            'num_guests' => 2,
            'is_blocked' => false,
            'total_price' => 300,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        // Cambiar a bloqueado
        $updated = $this->service->updateReservation($reservation->id, [
            'is_blocked' => true,
        ]);

        $this->assertTrue($updated->is_blocked);
        $this->assertEquals(0, $updated->total_price);
        $this->assertEquals(Client::DNI_BLOCK, $updated->client->dni);
        $this->assertNull($updated->pending_until);
    }

    public function test_convert_blocked_to_regular_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'is_blocked' => true,
            'total_price' => 0,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'num_guests' => 2,
        ]);

        // Cambiar a reserva normal
        $updated = $this->service->updateReservation($reservation->id, [
            'is_blocked' => false,
            'client' => [
                'name' => 'Real Client',
                'dni' => '55555555',
            ],
            'num_guests' => 2,
        ]);

        $this->assertFalse($updated->is_blocked);
        $this->assertGreaterThan(0, $updated->total_price);
        $this->assertNotEquals(Client::DNI_BLOCK, $updated->client->dni);
        $this->assertNotNull($updated->pending_until);
        $this->assertTrue($updated->pending_until->isFuture());
    }

    public function test_blocked_reservation_can_be_confirmed_with_zero_deposit(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'is_blocked' => true,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'deposit_amount' => 0,
        ]);

        // Una reserva bloqueada con deposit_amount=0 puede confirmarse
        $confirmed = $this->service->confirm($reservation->id, []);

        $this->assertEquals(Reservation::STATUS_CONFIRMED, $confirmed->status);
    }

    public function test_multiple_blocks_same_cabin(): void
    {
        // Crear múltiples bloqueos en la misma cabaña
        $block1 = $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ]);

        $block2 = $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(5)->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(7)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ]);

        // Intentar reservar en espacio entre bloqueos (debe funcionar)
        $reservation = $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(4)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Between Blocks',
                'dni' => '66666666',
            ],
        ]);

        $this->assertFalse($reservation->is_blocked);
        $this->assertGreaterThan(0, $reservation->total_price);
    }

    public function test_block_with_pending_hours_null(): void
    {
        $reservation = $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ]);

        // Bloqueos no deben tener pending_until
        $this->assertNull($reservation->pending_until);
    }

    public function test_block_doesnt_affect_other_cabins(): void
    {
        // Bloquear cabaña principal
        $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'is_blocked' => true,
        ]);

        // La otra cabaña debería estar disponible en las mismas fechas
        $reservation = $this->service->createReservation([
            'cabin_id' => $this->otherCabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Other Cabin',
                'dni' => '77777777',
            ],
        ]);

        $this->assertFalse($reservation->is_blocked);
        $this->assertGreaterThan(0, $reservation->total_price);
    }

    // ============= autoCalcellExpiredPending() - Cancelación automática de reservas expiradas =============

    public function test_reservation_pending_status_expires_automatically(): void
    {
        // Crear una reserva con pending_until en el pasado
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(1), // Vencida hace 1 hora
        ]);

        // Verificar que está marcada como expirada
        $this->assertTrue($reservation->fresh()->isPendingExpired());
    }

    public function test_auto_cancel_single_expired_pending_reservation(): void
    {
        // Crear una reserva con pending_until en el pasado
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(1), // Vencida hace 1 hora
        ]);

        // Ejecutar cancelación automática
        $result = $this->service->autoCalcellExpiredPending();

        // Verificar que se canceló correctamente
        $this->assertEquals(1, $result['cancelled']);
        $this->assertEquals(0, $result['failed']);

        // Verificar que la reserva ahora está cancelada
        $reservation->refresh();
        $this->assertTrue($reservation->isCancelled());
        $this->assertNull($reservation->pending_until);
    }

    public function test_auto_cancel_multiple_expired_pending_reservations(): void
    {
        // Crear tres reservas con pending_until expirado
        $reservation1 = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(2),
        ]);

        $reservation2 = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->otherCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(1),
        ]);

        $reservation3 = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subMinutes(30),
        ]);

        // Ejecutar cancelación automática
        $result = $this->service->autoCalcellExpiredPending();

        // Verificar que se cancelaron todas
        $this->assertEquals(3, $result['cancelled']);
        $this->assertEquals(0, $result['failed']);

        // Verificar que todas las reservas están canceladas
        foreach ([$reservation1, $reservation2, $reservation3] as $reservation) {
            $reservation->refresh();
            $this->assertTrue($reservation->isCancelled());
        }
    }

    public function test_auto_cancel_ignores_non_expired_pending_reservations(): void
    {
        // Crear una reserva expirada
        $expiredReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(1),
        ]);

        // Crear una reserva pendiente que AÚN no expira
        $validReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->otherCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->addHours(48), // Vence en 48 horas
        ]);

        // Ejecutar cancelación automática
        $result = $this->service->autoCalcellExpiredPending();

        // Verificar que solo se canceló la expirada
        $this->assertEquals(1, $result['cancelled']);
        $this->assertEquals(0, $result['failed']);

        // Verificar que la expirada está cancelada
        $expiredReservation->refresh();
        $this->assertTrue($expiredReservation->isCancelled());

        // Verificar que la válida sigue pendiente
        $validReservation->refresh();
        $this->assertTrue($validReservation->isPendingConfirmation());
    }

    public function test_auto_cancel_ignores_confirmed_reservations(): void
    {
        // Crear una reserva confirmada (sin pending_until o con fecha pasada)
        $confirmedReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'pending_until' => null,
        ]);

        // Ejecutar cancelación automática
        $result = $this->service->autoCalcellExpiredPending();

        // Verificar que no se canceló nada
        $this->assertEquals(0, $result['cancelled']);
        $this->assertEquals(0, $result['failed']);

        // Verificar que la reserva sigue confirmada
        $confirmedReservation->refresh();
        $this->assertTrue($confirmedReservation->isConfirmed());
    }

    public function test_auto_cancel_ignores_reservations_without_pending_until(): void
    {
        // Crear una reserva pendiente sin pending_until (debería seguir pendiente indefinidamente)
        $pendingReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => null,
        ]);

        // Ejecutar cancelación automática
        $result = $this->service->autoCalcellExpiredPending();

        // Verificar que no se canceló nada
        $this->assertEquals(0, $result['cancelled']);
        $this->assertEquals(0, $result['failed']);

        // Verificar que la reserva sigue pendiente
        $pendingReservation->refresh();
        $this->assertTrue($pendingReservation->isPendingConfirmation());
    }

    public function test_expired_pending_reservation_does_not_block_availability(): void
    {
        // Crear una reserva expirada
        $expiredReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(1),
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
        ]);

        // Verificar que está expirada
        $this->assertTrue($expiredReservation->isPendingExpired());

        // Verificar que NO bloquea disponibilidad una vez expirada
        $this->assertFalse($expiredReservation->fresh()->blocksAvailability());

        // Debería poderse hacer una nueva reserva en esas fechas
        $newReservation = $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'New Booking',
                'dni' => '99999999',
            ],
        ]);

        $this->assertNotNull($newReservation->id);
        $this->assertTrue($newReservation->isPendingConfirmation());
    }

    public function test_non_expired_pending_reservation_still_blocks_availability(): void
    {
        // Crear una reserva pendiente VÁLIDA (no expirada)
        $validReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->addHours(48),
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
        ]);

        // Verificar que NO está expirada
        $this->assertFalse($validReservation->isPendingExpired());

        // Verificar que SÍ bloquea disponibilidad
        $this->assertTrue($validReservation->fresh()->blocksAvailability());

        // NO debería poderse hacer una nueva reserva en esas fechas
        $this->expectException(ValidationException::class);

        $this->service->createReservation([
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            'client' => [
                'name' => 'Conflicting Booking',
                'dni' => '88888888',
            ],
        ]);
    }

    public function test_auto_cancel_with_mixed_statuses(): void
    {
        // Crear múltiples reservas con diferentes estados
        // 1. Expirada (debe cancelarse)
        $expiredPending = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->subHours(2),
        ]);

        // 2. Válida (no debe cancelarse)
        $validPending = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->addHours(24),
        ]);

        // 3. Confirmada (no debe cancelarse)
        $confirmed = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->otherCabin->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'pending_until' => null,
        ]);

        // 4. Finalizada (no debe cancelarse)
        $finished = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->otherCabin->id,
            'status' => Reservation::STATUS_FINISHED,
            'pending_until' => null,
        ]);

        // 5. Ya cancelada (no debe intentarse cancelar de nuevo)
        $alreadyCancelled = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'status' => Reservation::STATUS_CANCELLED,
            'pending_until' => null,
        ]);

        // Ejecutar cancelación automática
        $result = $this->service->autoCalcellExpiredPending();

        // Verificar que solo se canceló la expirada
        $this->assertEquals(1, $result['cancelled']);
        $this->assertEquals(0, $result['failed']);

        // Verificar estados finales
        $expiredPending->refresh();
        $this->assertTrue($expiredPending->isCancelled());

        $validPending->refresh();
        $this->assertTrue($validPending->isPendingConfirmation());

        $confirmed->refresh();
        $this->assertTrue($confirmed->isConfirmed());

        $finished->refresh();
        $this->assertTrue($finished->isFinished());

        $alreadyCancelled->refresh();
        $this->assertTrue($alreadyCancelled->isCancelled());
    }
}
