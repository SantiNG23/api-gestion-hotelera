<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PriceGroup;
use App\Models\PriceRange;
use Carbon\Carbon;
use Tests\TestCase;

class PriceRangeApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_list_price_ranges(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        PriceRange::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/price-ranges');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_price_range(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $data = [
            'price_group_id' => $priceGroup->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-ranges', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.price_group_id', $priceGroup->id);

        $this->assertDatabaseHas('price_ranges', [
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup->id,
        ]);
    }

    public function test_can_create_overlapping_price_ranges(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        // Crear primer rango
        $firstRange = [
            'price_group_id' => $priceGroup->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(10)->format('Y-m-d'),
        ];

        $response1 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-ranges', $firstRange);

        $response1->assertStatus(201);

        // Crear rango solapado (esto ahora es permitido)
        $secondRange = [
            'price_group_id' => $priceGroup->id,
            'start_date' => Carbon::tomorrow()->addDays(5)->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(15)->format('Y-m-d'),
        ];

        $response2 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-ranges', $secondRange);

        $response2->assertStatus(201);

        // Verificar que ambos rangos existen
        $this->assertDatabaseCount('price_ranges', 2);
    }

    public function test_cannot_create_price_range_without_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-ranges', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price_group_id', 'start_date', 'end_date']);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $data = [
            'price_group_id' => $priceGroup->id,
            'start_date' => Carbon::tomorrow()->addDays(5)->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->format('Y-m-d'),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-ranges', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_can_update_price_range(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        $priceRange = PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup->id,
        ]);

        $newEndDate = Carbon::parse($priceRange->start_date)->addDays(14)->format('Y-m-d');

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/price-ranges/{$priceRange->id}", [
                'end_date' => $newEndDate,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.end_date', $newEndDate);
    }

    public function test_can_delete_price_range(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        $priceRange = PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/price-ranges/{$priceRange->id}");

        $this->assertApiResponse($response);
        $this->assertSoftDeleted('price_ranges', ['id' => $priceRange->id]);
    }

    public function test_can_get_applicable_rates_with_single_price_group(): void
    {
        $priceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_per_night' => 100.00,
            'priority' => 0,
        ]);

        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays(5);

        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/price-ranges/applicable-rates?start_date=' . $startDate->format('Y-m-d') . '&end_date=' . $endDate->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonPath('data.start_date', $startDate->format('Y-m-d'))
            ->assertJsonPath('data.end_date', $endDate->format('Y-m-d'));

        // Verificar que todos los días tienen el precio correcto
        $rates = $response->json('data.rates');
        $this->assertCount(6, $rates); // 6 días (inclusivo)
        foreach ($rates as $rate) {
            $this->assertEquals(100.00, $rate['price']);
            $this->assertEquals($priceGroup->name, $rate['group_name']);
        }
    }

    public function test_applicable_rates_selects_highest_priority(): void
    {
        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays(5);

        // Crear dos grupos de precio con diferentes prioridades
        $basePriceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_per_night' => 100.00,
            'priority' => 0,
        ]);

        $premiumPriceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_per_night' => 200.00,
            'priority' => 10,
        ]);

        // Crear rango de base que cubre todo el período
        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $basePriceGroup->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Crear rango premium que cubre solo los últimos 3 días
        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $premiumPriceGroup->id,
            'start_date' => $startDate->copy()->addDays(3),
            'end_date' => $endDate,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/price-ranges/applicable-rates?start_date=' . $startDate->format('Y-m-d') . '&end_date=' . $endDate->format('Y-m-d'));

        $response->assertStatus(200);

        $rates = $response->json('data.rates');

        // Los primeros 3 días deben ser el precio base
        for ($i = 0; $i < 3; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $this->assertEquals(100.00, $rates[$date]['price']);
        }

        // Los últimos 3 días deben ser el precio premium
        for ($i = 3; $i < 6; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $this->assertEquals(200.00, $rates[$date]['price']);
        }
    }

    public function test_applicable_rates_uses_created_at_tiebreaker(): void
    {
        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays(2);

        // Crear dos grupos con la misma prioridad
        $priceGroup1 = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_per_night' => 100.00,
            'priority' => 5,
        ]);

        $priceGroup2 = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_per_night' => 150.00,
            'priority' => 5,
        ]);

        // Crear primer rango (será el más antiguo)
        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup1->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Crear segundo rango (será el más nuevo)
        sleep(1); // Esperar para garantizar que created_at sea diferente
        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup2->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/price-ranges/applicable-rates?start_date=' . $startDate->format('Y-m-d') . '&end_date=' . $endDate->format('Y-m-d'));

        $response->assertStatus(200);

        $rates = $response->json('data.rates');

        // El precio ganador debe ser del rango más nuevo (priceGroup2)
        foreach ($rates as $rate) {
            $this->assertEquals(150.00, $rate['price']);
            $this->assertEquals($priceGroup2->name, $rate['group_name']);
        }
    }

    public function test_applicable_rates_returns_fallback_for_no_matches(): void
    {
        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays(2);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/price-ranges/applicable-rates?start_date=' . $startDate->format('Y-m-d') . '&end_date=' . $endDate->format('Y-m-d'));

        $response->assertStatus(200);
        
        $rates = $response->json('data.rates');
        $this->assertCount(3, $rates);
        foreach ($rates as $rate) {
            $this->assertEquals(0.0, $rate['price']);
            $this->assertEquals('Sin tarifa configurada', $rate['group_name']);
        }
    }
}

