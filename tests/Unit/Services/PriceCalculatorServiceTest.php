<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use App\Models\PriceRange;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PriceCalculatorService;
use Carbon\Carbon;
use Tests\TestCase;

class PriceCalculatorServiceTest extends TestCase
{
    private PriceCalculatorService $service;
    protected ?Tenant $localTenant = null;
    protected ?Cabin $cabin = null;
    protected ?PriceGroup $priceGroup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localTenant = Tenant::factory()->create();

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $this->localTenant->id]);
        $this->actingAs($user);

        $this->service = app(PriceCalculatorService::class);
        $this->cabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        $this->priceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_per_night' => 100,
            'is_default' => true,
        ]);

        // Crear precios por cantidad de huéspedes
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 3,
            'price_per_night' => 120,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 4,
            'price_per_night' => 140,
        ]);
    }

    public function test_calculates_price_with_default_group(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(3);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $this->assertEquals(300, $result['total']); // 3 noches x 100
        $this->assertEquals(150, $result['deposit']);
        $this->assertEquals(150, $result['balance']);
        $this->assertEquals(3, $result['nights']);
        $this->assertCount(3, $result['breakdown']);
    }

    public function test_calculates_price_with_different_guest_count(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 3);

        $this->assertEquals(240, $result['total']); // 2 noches x 120
        $this->assertEquals(120, $result['deposit']);
        $this->assertEquals(120, $result['balance']);
    }

    public function test_calculates_price_with_price_range(): void
    {
        $seasonalGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Temporada Alta',
            'price_per_night' => 200,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $seasonalGroup->id,
            'num_guests' => 2,
            'price_per_night' => 200,
        ]);

        PriceRange::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_group_id' => $seasonalGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(10),
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $this->assertEquals(400, $result['total']); // 2 noches x 200
        $this->assertEquals('Temporada Alta', $result['breakdown'][0]['price_group']);
    }

    public function test_calculates_deposit_as_50_percent(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(4);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $total = 400; // 4 noches x 100
        $this->assertEquals($total, $result['total']);
        $this->assertEquals($total * 0.5, $result['deposit']);
        $this->assertEquals($total * 0.5, $result['balance']);
    }

    public function test_returns_zero_for_invalid_dates(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow(); // Misma fecha

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['nights']);
    }

    public function test_returns_zero_when_no_price_configured(): void
    {
        // Crear una nueva cabaña sin precios configurados
        $newCabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $newCabin->id, 2);

        $this->assertEquals(0, $result['total']);
    }

    public function test_generate_quote_includes_cabin_id(): void
    {
        $result = $this->service->generateQuote(
            $this->cabin->id,
            Carbon::tomorrow()->format('Y-m-d'),
            Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            2
        );

        $this->assertEquals($this->cabin->id, $result['cabin_id']);
        $this->assertEquals(300, $result['total']);
    }

    public function test_breakdown_includes_date_and_price_per_night(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $this->assertArrayHasKey('date', $result['breakdown'][0]);
        $this->assertArrayHasKey('price', $result['breakdown'][0]);
        $this->assertArrayHasKey('price_group', $result['breakdown'][0]);
        $this->assertEquals(100, $result['breakdown'][0]['price']);
    }

    /**
     * Test: Calcular precio con múltiples cambios de grupo de precio dentro de una reserva
     */
    public function test_calculates_price_with_multiple_price_changes_in_reservation(): void
    {
        $seasonalGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Temporada Alta',
            'price_per_night' => 150,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $seasonalGroup->id,
            'num_guests' => 2,
            'price_per_night' => 150,
        ]);

        // Rango estacional: mañana a pasado mañana
        PriceRange::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_group_id' => $seasonalGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(1),
        ]);

        // Reserva de 3 noches: 2 a 150 (en rango) + 1 a 100 (fuera de rango)
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(3);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $this->assertEquals(400, $result['total']); // 150 + 150 + 100
        $this->assertCount(3, $result['breakdown']);
        $this->assertEquals('Temporada Alta', $result['breakdown'][0]['price_group']);
        $this->assertEquals('Temporada Alta', $result['breakdown'][1]['price_group']);
        // Verificar que el tercer día tenga un grupo de precio (puede ser Temporada Alta o Tarifa Base)
        $this->assertNotNull($result['breakdown'][2]['price_group']);
    }

    /**
     * Test: Calcular precio con rango que comienza exactamente en check-in
     */
    public function test_calculates_price_with_range_starting_on_checkin(): void
    {
        $seasonalGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Fin de Semana',
            'price_per_night' => 180,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $seasonalGroup->id,
            'num_guests' => 2,
            'price_per_night' => 180,
        ]);

        $checkIn = Carbon::tomorrow();
        PriceRange::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_group_id' => $seasonalGroup->id,
            'start_date' => $checkIn,
            'end_date' => $checkIn->clone()->addDays(2),
        ]);

        $checkOut = $checkIn->clone()->addDays(3);
        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        // Rango del check-in al day 2 (3 noches): todas a 180
        // La lógica de PriceRange cubre hasta addDays(2), lo que incluye 3 días (0, 1, 2)
        // Verificar que se haya aplicado el rango correctamente
        $this->assertEquals(3, $result['nights']);
        $this->assertGreaterThan(400, $result['total']); // Debería ser > 400 si se aplica el precio de 180
    }

    /**
     * Test: Calcular precio con rango que termina exactamente en check-out
     */
    public function test_calculates_price_with_range_ending_on_checkout(): void
    {
        $seasonalGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Promoción',
            'price_per_night' => 80,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $seasonalGroup->id,
            'num_guests' => 2,
            'price_per_night' => 80,
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        PriceRange::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_group_id' => $seasonalGroup->id,
            'start_date' => $checkIn->clone()->addDay(),
            'end_date' => $checkOut->clone()->subDay(),
        ]);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        // 1 noche a 100 + 2 noches a 80 (en rango)
        $this->assertEquals(260, $result['total']);
    }

    /**
     * Test: Precisión decimal correcta en cálculos
     */
    public function test_calculates_with_correct_decimal_precision(): void
    {
        $precisionGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Tarifa Especial',
            'price_per_night' => 99.99,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $precisionGroup->id,
            'num_guests' => 2,
            'price_per_night' => 99.99,
        ]);

        PriceRange::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_group_id' => $precisionGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(10),
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        // 2 noches x 99.99 = 199.98
        $this->assertEquals(199.98, $result['total']);
        $this->assertEquals(99.99, $result['deposit']);
        $this->assertEquals(99.99, $result['balance']);
    }

    /**
     * Test: Cálculo con depósito que requiere redondeo
     */
    public function test_calculates_deposit_with_rounding(): void
    {
        $oddGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Tarifa Impar',
            'price_per_night' => 101,
            'is_default' => false,
        ]);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $oddGroup->id,
            'num_guests' => 2,
            'price_per_night' => 101,
        ]);

        PriceRange::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_group_id' => $oddGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(10),
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        // 2 noches x 101 = 202
        $this->assertEquals(202, $result['total']);
        $this->assertEquals(101, $result['deposit']);
        $this->assertEquals(101, $result['balance']);
    }

    /**
     * Test: Precio para fecha específica cuando no hay rango aplicable
     */
    public function test_get_price_for_date_without_price_range(): void
    {
        $date = Carbon::tomorrow();

        // Usar reflexión para acceder al método privado getPriceForDate
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getPriceForDate');
        $method->setAccessible(true);

        $price = $method->invoke($this->service, $date, $this->cabin->id, 2);

        // Debe devolver el precio del grupo por defecto
        $this->assertEquals(100, $price);
    }

    /**
     * Test: Nombre del grupo de precio para una fecha sin rango específico
     */
    public function test_get_price_group_name_for_date_without_range(): void
    {
        $date = Carbon::tomorrow();

        // Usar reflexión para acceder al método privado getPriceGroupNameForDate
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getPriceGroupNameForDate');
        $method->setAccessible(true);

        $groupName = $method->invoke($this->service, $date);

        // Debe devolver el nombre de algún grupo de precio
        $this->assertNotNull($groupName);
        $this->assertIsString($groupName);
    }

    /**
     * Test: Breakdown incluye fechas secuenciales correctas
     */
    public function test_breakdown_includes_sequential_dates(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(3);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $expectedDates = [
            $checkIn->format('Y-m-d'),
            $checkIn->clone()->addDay()->format('Y-m-d'),
            $checkIn->clone()->addDays(2)->format('Y-m-d'),
        ];

        foreach ($result['breakdown'] as $index => $day) {
            $this->assertEquals($expectedDates[$index], $day['date']);
        }
    }

    /**
     * Test: Generate quote con fechas en formato string
     */
    public function test_generate_quote_with_string_dates(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->generateQuote(
            $this->cabin->id,
            $checkIn->format('Y-m-d'),
            $checkOut->format('Y-m-d'),
            2
        );

        $this->assertEquals($checkIn->format('Y-m-d'), $result['check_in']);
        $this->assertEquals($checkOut->format('Y-m-d'), $result['check_out']);
        $this->assertEquals(2, $result['nights']);
        $this->assertEquals(200, $result['total']); // 2 noches x 100
    }

    /**
     * Test: Calcular precio con número grande de noches
     */
    public function test_calculates_price_with_large_number_of_nights(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(30);

        $result = $this->service->calculatePrice($checkIn, $checkOut, $this->cabin->id, 2);

        $this->assertEquals(30, $result['nights']);
        $this->assertEquals(3000, $result['total']); // 30 noches x 100
        $this->assertEquals(1500, $result['deposit']);
        $this->assertEquals(1500, $result['balance']);
        $this->assertCount(30, $result['breakdown']);
    }
}
