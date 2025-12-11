<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PriceGroup;
use Tests\TestCase;

class PriceGroupApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_list_price_groups(): void
    {
        PriceGroup::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/price-groups');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_price_group(): void
    {
        $data = [
            'name' => 'Temporada Alta',
            'price_per_night' => 150.50,
            'is_default' => false,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-groups', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Temporada Alta')
            ->assertJsonPath('data.price_per_night', 150.50);

        $this->assertDatabaseHas('price_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Temporada Alta',
        ]);
    }

    public function test_can_create_price_group_with_priority(): void
    {
        $data = [
            'name' => 'Temporada Premium',
            'price_per_night' => 250.00,
            'priority' => 20,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-groups', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Temporada Premium')
            ->assertJsonPath('data.priority', 20);

        $this->assertDatabaseHas('price_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Temporada Premium',
            'priority' => 20,
        ]);
    }

    public function test_price_group_default_priority_is_zero(): void
    {
        $data = [
            'name' => 'Tarifa Base',
            'price_per_night' => 100,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-groups', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.priority', 0);

        $this->assertDatabaseHas('price_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Tarifa Base',
            'priority' => 0,
        ]);
    }

    public function test_can_create_default_price_group(): void
    {
        $data = [
            'name' => 'Tarifa Base',
            'price_per_night' => 100,
            'is_default' => true,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-groups', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_default', true);
    }

    public function test_setting_new_default_unsets_previous(): void
    {
        $existingDefault = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_default' => true,
        ]);

        $data = [
            'name' => 'Nueva Tarifa Default',
            'price_per_night' => 120,
            'is_default' => true,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-groups', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('price_groups', [
            'id' => $existingDefault->id,
            'is_default' => false,
        ]);
    }

    public function test_cannot_create_price_group_without_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-groups', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price_per_night']);
    }

    public function test_can_update_price_group(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/price-groups/{$priceGroup->id}", [
                'name' => 'Temporada Media',
                'price_per_night' => 180,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.name', 'Temporada Media');
        $response->assertJsonPath('data.price_per_night', 180.0);
    }

    public function test_can_delete_price_group(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/price-groups/{$priceGroup->id}");

        $this->assertApiResponse($response);
        $this->assertSoftDeleted('price_groups', ['id' => $priceGroup->id]);
    }
}

