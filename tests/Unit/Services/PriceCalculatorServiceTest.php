<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->localTenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $this->localTenant->id]);
        $this->actingAs($user);
        
        $this->service = app(PriceCalculatorService::class);
    }

    public function test_calculates_price_with_default_group(): void
    {
        PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_per_night' => 100,
            'is_default' => true,
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(3);

        $result = $this->service->calculatePrice($checkIn, $checkOut);

        $this->assertEquals(300, $result['total']);
        $this->assertEquals(150, $result['deposit']);
        $this->assertEquals(150, $result['balance']);
        $this->assertEquals(3, $result['nights']);
        $this->assertCount(3, $result['breakdown']);
    }

    public function test_calculates_price_with_price_range(): void
    {
        $priceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Temporada Alta',
            'price_per_night' => 200,
            'is_default' => false,
        ]);

        PriceRange::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_group_id' => $priceGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(10),
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut);

        $this->assertEquals(400, $result['total']); // 2 noches x 200
        $this->assertEquals('Temporada Alta', $result['breakdown'][0]['price_group']);
    }

    public function test_calculates_deposit_as_50_percent(): void
    {
        PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_per_night' => 150,
            'is_default' => true,
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(4);

        $result = $this->service->calculatePrice($checkIn, $checkOut);

        $total = 600; // 4 noches x 150
        $this->assertEquals($total, $result['total']);
        $this->assertEquals($total * 0.5, $result['deposit']);
        $this->assertEquals($total * 0.5, $result['balance']);
    }

    public function test_returns_zero_for_invalid_dates(): void
    {
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow(); // Misma fecha

        $result = $this->service->calculatePrice($checkIn, $checkOut);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['nights']);
    }

    public function test_returns_zero_when_no_price_configured(): void
    {
        // Sin grupos de precio creados
        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut);

        $this->assertEquals(0, $result['total']);
    }

    public function test_generate_quote_includes_cabin_id(): void
    {
        PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'price_per_night' => 100,
            'is_default' => true,
        ]);

        $result = $this->service->generateQuote(
            1,
            Carbon::tomorrow()->format('Y-m-d'),
            Carbon::tomorrow()->addDays(3)->format('Y-m-d')
        );

        $this->assertEquals(1, $result['cabin_id']);
        $this->assertEquals(300, $result['total']);
    }

    public function test_breakdown_includes_date_and_price_per_night(): void
    {
        PriceGroup::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'name' => 'Tarifa Base',
            'price_per_night' => 120,
            'is_default' => true,
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = Carbon::tomorrow()->addDays(2);

        $result = $this->service->calculatePrice($checkIn, $checkOut);

        $this->assertArrayHasKey('date', $result['breakdown'][0]);
        $this->assertArrayHasKey('price', $result['breakdown'][0]);
        $this->assertArrayHasKey('price_group', $result['breakdown'][0]);
        $this->assertEquals(120, $result['breakdown'][0]['price']);
        $this->assertEquals('Tarifa Base', $result['breakdown'][0]['price_group']);
    }
}

