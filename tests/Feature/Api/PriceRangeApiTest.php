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

    public function test_cannot_create_overlapping_price_range(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        // Crear primer rango
        PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(10),
        ]);

        // Intentar crear rango solapado
        $data = [
            'price_group_id' => $priceGroup->id,
            'start_date' => Carbon::tomorrow()->addDays(5)->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(15)->format('Y-m-d'),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-ranges', $data);

        $response->assertStatus(422);
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
}

