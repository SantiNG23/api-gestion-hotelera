<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use App\Models\PriceRange;
use App\Models\Tenant;
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
        $this->service = new PriceCalculatorService();
        $this->localTenant = Tenant::factory()->create();
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
}

