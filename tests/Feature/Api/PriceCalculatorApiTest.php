<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use App\Models\PriceRange;
use App\Models\Tenant;
use App\Models\User;
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
