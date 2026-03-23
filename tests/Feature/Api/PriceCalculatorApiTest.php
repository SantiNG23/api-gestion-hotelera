<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\Client;
use App\Models\PriceGroup;
use App\Models\PriceRange;
use App\Models\Reservation;
use App\Models\Tenant;
use Carbon\Carbon;
use Tests\TestCase;

class PriceCalculatorApiTest extends TestCase
{
    private Cabin $cabin;
    private PriceGroup $defaultPriceGroup;
    private PriceGroup $seasonalPriceGroup;
    private Tenant $otherTenant;
    private Cabin $otherTenantCabin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();

        // Crear cabaña para este tenant
        $this->cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        // Grupo de precio por defecto
        $this->defaultPriceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tarifa Base',
            'price_per_night' => 100,
            'is_default' => true,
        ]);

        // Precios por cantidad de huéspedes para el grupo por defecto
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->defaultPriceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->defaultPriceGroup->id,
            'num_guests' => 3,
            'price_per_night' => 120,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->defaultPriceGroup->id,
            'num_guests' => 4,
            'price_per_night' => 140,
        ]);

        // Grupo de precio estacional
        $this->seasonalPriceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Temporada Alta',
            'price_per_night' => 200,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->seasonalPriceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 200,
        ]);

        // Crear tenant ajeno para validar aislamiento
        $this->otherTenant = Tenant::factory()->create();
        $this->otherTenantCabin = Cabin::factory()->create([
            'tenant_id' => $this->otherTenant->id,
        ]);
    }

    /**
     * Test: Calcular precio correctamente con grupo por defecto
     */
    public function test_calculate_price_with_default_group(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.cabin_id', $this->cabin->id);
        $response->assertJsonPath('data.cabin_name', $this->cabin->name);
        $response->assertJsonPath('data.nights', 3.0); // JSON convierte a float
        $response->assertJsonPath('data.total_price', 300.0); // 3 noches x 100
        $response->assertJsonPath('data.deposit_amount', 150.0); // 50%
        $response->assertJsonPath('data.balance_amount', 150.0); // 50%
        $response->assertJsonStructure([
            'data' => [
                'cabin_id',
                'cabin_name',
                'check_in_date',
                'check_out_date',
                'num_guests',
                'nights',
                'total_price',
                'deposit_amount',
                'balance_amount',
                'pricing_breakdown',
            ],
        ]);
    }

    /**
     * Test: Calcular precio con diferentes cantidades de huéspedes
     */
    public function test_calculate_price_with_different_guest_counts(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 3,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.num_guests', 3);
        $response->assertJsonPath('data.nights', 2.0);
        $response->assertJsonPath('data.total_price', 240.0); // 2 noches x 120
        $response->assertJsonPath('data.deposit_amount', 120.0);
    }

    /**
     * Test: Calcular precio con rango de precio estacional
     */
    public function test_calculate_price_with_seasonal_price_range(): void
    {
        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $this->seasonalPriceGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(10),
        ]);

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total_price', 400.0); // 2 noches x 200 (precio estacional)
        $response->assertJsonPath('data.deposit_amount', 200.0);
        // Verificar que el breakdown existe
        $response->assertJsonStructure([
            'data' => ['pricing_breakdown'],
        ]);
    }

    /**
     * Test: Calcular precio con múltiples cambios de grupo de precio
     */
    public function test_calculate_price_with_multiple_price_changes(): void
    {
        // Rango 1: Mañana a dentro de 2 días = Temporada Alta
        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $this->seasonalPriceGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(1),
        ]);

        // Rango 2: Dentro de 2 días a dentro de 4 días = Tarifa Base (sin rango específico)

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.nights', 3.0);
        // Día 1: 200 (Temporada Alta), Día 2: 200 (Temporada Alta), Día 3: 100 (Tarifa Base)
        $response->assertJsonPath('data.total_price', 500.0); // 200 + 200 + 100
        // Verificar breakdown con cambios de grupo
        $breakdown = $response->json('data.pricing_breakdown');
        $this->assertIsArray($breakdown);
    }

    /**
     * Test: Rechaza cuando la cabaña no existe
     */
    public function test_reject_calculate_price_cabin_not_found(): void
    {
        $data = [
            'cabin_id' => 99999,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cabin_id']);
    }

    public function test_reject_calculate_price_for_cabin_from_other_tenant(): void
    {
        $data = [
            'cabin_id' => $this->otherTenantCabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cabin_id']);
    }

    public function test_quote_rejects_cabin_from_other_tenant(): void
    {
        $data = [
            'cabin_id' => $this->otherTenantCabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cabin_id']);
    }

    public function test_quote_excludes_current_reservation_when_reservation_id_is_sent(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
                'reservation_id' => $reservation->id,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.is_available', true);
        $response->assertJsonPath('data.total', 300.0);
    }

    public function test_quote_marks_unavailable_when_overlapping_another_reservation(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow(),
            'check_out_date' => Carbon::tomorrow()->addDays(3),
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $currentReservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->addDays(5),
            'check_out_date' => Carbon::tomorrow()->addDays(6),
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
                'reservation_id' => $currentReservation->id,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.is_available', false);
    }

    public function test_quote_rejects_reservation_id_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $otherCabin = Cabin::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $otherReservation = Reservation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
            'cabin_id' => $otherCabin->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
                'reservation_id' => $otherReservation->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation_id']);
    }

    public function test_calculate_price_prefers_highest_priority_range_when_overlapping(): void
    {
        $baseGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Base overlap',
            'price_per_night' => 110,
            'priority' => 1,
            'is_default' => false,
        ]);
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $baseGroup->id,
            'num_guests' => 2,
            'price_per_night' => 110,
        ]);

        $premiumGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Premium overlap',
            'price_per_night' => 220,
            'priority' => 10,
            'is_default' => false,
        ]);
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $premiumGroup->id,
            'num_guests' => 2,
            'price_per_night' => 220,
        ]);

        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays(2);

        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $baseGroup->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $premiumGroup->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => $startDate->format('Y-m-d'),
                'check_out_date' => $endDate->copy()->addDay()->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.total_price', 660.0);
    }

    /**
     * Test: Rechaza cuando se excede la capacidad de la cabaña
     */
    public function test_reject_calculate_price_exceeds_cabin_capacity(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 5, // Excede capacidad de 4
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['num_guests']);
    }

    public function test_quote_rejects_when_exceeding_cabin_capacity(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 5,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['num_guests']);
    }

    public function test_calculate_price_rejects_missing_tariff_configuration_with_422(): void
    {
        $cabinWithoutTariff = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', [
                'cabin_id' => $cabinWithoutTariff->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pricing']);
    }

    public function test_quote_rejects_missing_tariff_configuration_with_422(): void
    {
        $cabinWithoutTariff = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', [
                'cabin_id' => $cabinWithoutTariff->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pricing']);
    }

    /**
     * Test: Rechaza cuando el check-in es en el pasado
     */
    public function test_reject_calculate_price_check_in_in_past(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::yesterday()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_in_date']);
    }

    /**
     * Test: Rechaza cuando el formato de fecha es incorrecto
     */
    public function test_reject_calculate_price_invalid_date_format(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => '2025-13-45', // Formato inválido
            'check_out_date' => Carbon::tomorrow()->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_in_date']);
    }

    /**
     * Test: Rechaza cuando check-out es antes o igual a check-in
     */
    public function test_reject_calculate_price_checkout_before_checkin(): void
    {
        $checkIn = Carbon::tomorrow();
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkIn->format('Y-m-d'), // Misma fecha
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_out_date']);
    }

    /**
     * Test: Rechaza sin autenticación
     */
    public function test_reject_calculate_price_without_authentication(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(401);
    }

    /**
     * Test: Rechaza cuando falta el campo num_guests
     */
    public function test_reject_calculate_price_missing_num_guests(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            // Falta num_guests
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['num_guests']);
    }

    /**
     * Test: Rechaza cuando falta el campo cabin_id
     */
    public function test_reject_calculate_price_missing_cabin_id(): void
    {
        $data = [
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
            // Falta cabin_id
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cabin_id']);
    }

    /**
     * Test: Rechaza cuando num_guests es menor a 2
     */
    public function test_reject_calculate_price_num_guests_below_minimum(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 1, // Mínimo es 2
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['num_guests']);
    }

    /**
     * Test: Rechaza cuando num_guests es un valor no numérico
     */
    public function test_reject_calculate_price_num_guests_not_numeric(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 'dos', // No es numérico
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['num_guests']);
    }

    /**
     * Test: Rechaza cuando cabin_id es negativo
     */
    public function test_reject_calculate_price_negative_cabin_id(): void
    {
        $data = [
            'cabin_id' => -1,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $response->assertStatus(422);
    }

    /**
     * Test: Calcular precio cuando no hay precios configurados para cantidad de huéspedes
     */
    public function test_calculate_price_no_price_configured_for_guest_count(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'num_guests' => 5, // No está configurado (capacidad máxima es 4)
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        // Debe rechazar por exceder capacidad
        $response->assertStatus(422);
    }

    /**
     * Test: Calcular precio devuelve breakdown detallado
     */
    public function test_calculate_price_includes_detailed_breakdown(): void
    {
        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $this->assertApiResponse($response);
        $breakdown = $response->json('data.pricing_breakdown');

        $this->assertIsArray($breakdown);
        $this->assertNotEmpty($breakdown);
        foreach ($breakdown as $day) {
            $this->assertArrayHasKey('date', $day);
            $this->assertArrayHasKey('price', $day);
            $this->assertArrayHasKey('price_group', $day);
            $this->assertEquals(100, $day['price']);
        }
    }

    /**
     * Test: Calcular precio con rango exacto en límite inicial
     */
    public function test_calculate_price_with_range_starting_on_checkin(): void
    {
        $checkIn = Carbon::tomorrow();

        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $this->seasonalPriceGroup->id,
            'start_date' => $checkIn,
            'end_date' => $checkIn->clone()->addDays(2),
        ]);

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkIn->clone()->addDays(3)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $this->assertApiResponse($response);
        // 3 días a 200 cada uno (en rango)
        $response->assertJsonPath('data.total_price', 600.0);
    }

    /**
     * Test: Calcular precio con precisión decimal correcta
     */
    public function test_calculate_price_with_correct_decimal_precision(): void
    {
        // Crear un precio que resulte en decimales cuando se divide por depósito
        $precisionGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tarifa Precisión',
            'price_per_night' => 99.99,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $precisionGroup->id,
            'num_guests' => 2,
            'price_per_night' => 99.99,
        ]);

        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $precisionGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(10),
        ]);

        $data = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
            'check_out_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'num_guests' => 2,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $data);

        $this->assertApiResponse($response);
        // 2 noches x 99.99 = 199.98
        $this->assertEquals(199.98, $response->json('data.total_price'));
        $this->assertEquals(99.99, $response->json('data.deposit_amount'));
        $this->assertEquals(99.99, $response->json('data.balance_amount'));
    }
}
