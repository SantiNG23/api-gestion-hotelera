<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CabinPriceByGuests;
use App\Models\Cabin;
use App\Models\PriceGroup;
use App\Models\PriceRange;
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
        // Verificar que se hace un hard delete (eliminación completa)
        $this->assertDatabaseMissing('price_groups', ['id' => $priceGroup->id]);
    }

    public function test_deleting_price_group_also_deletes_associated_records(): void
    {
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Crear rangos de precio asociados
        $priceRanges = PriceRange::factory()
            ->count(2)
            ->create([
                'price_group_id' => $priceGroup->id,
                'tenant_id' => $this->tenant->id,
            ]);
        
        // Crear precios de cabañas asociados
        $cabinPrices = CabinPriceByGuests::factory()
            ->count(3)
            ->create([
                'price_group_id' => $priceGroup->id,
                'tenant_id' => $this->tenant->id,
            ]);

        // Eliminar el grupo de precios
        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/price-groups/{$priceGroup->id}");

        $this->assertApiResponse($response);
        
        // Verificar que el grupo de precios se eliminó
        $this->assertDatabaseMissing('price_groups', ['id' => $priceGroup->id]);
        
        // Verificar que los rangos de precio también se eliminaron
        foreach ($priceRanges as $range) {
            $this->assertDatabaseMissing('price_ranges', ['id' => $range->id]);
        }
        
        // Verificar que los precios de cabañas también se eliminaron
        foreach ($cabinPrices as $price) {
            $this->assertDatabaseMissing('cabin_price_by_guests', ['id' => $price->id]);
        }
    }

    public function test_can_get_price_group_complete(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Crear precios para la cabaña en este grupo
        $prices = CabinPriceByGuests::factory()
            ->count(2)
            ->create([
                'cabin_id' => $cabin->id,
                'price_group_id' => $priceGroup->id,
                'tenant_id' => $this->tenant->id,
            ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/price-groups/{$priceGroup->id}/complete");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $priceGroup->id)
            ->assertJsonPath('data.name', $priceGroup->name)
            ->assertJsonPath('data.cabins_count', 1)
            ->assertJsonPath('data.prices_count', 2);
    }

    public function test_show_complete_returns_404_for_nonexistent_group(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/price-groups/99999/complete");

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Grupo de precio no encontrado');
    }

    public function test_can_update_price_group_complete(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $priceGroup = PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $updateData = [
            'name' => 'Temporada Alta Actualizada',
            'cabins' => [
                [
                    'cabin_id' => $cabin->id,
                    'prices' => [
                        ['num_guests' => 2, 'price_per_night' => 100.00],
                        ['num_guests' => 3, 'price_per_night' => 150.00],
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/price-groups/{$priceGroup->id}/complete", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Temporada Alta Actualizada');

        $this->assertDatabaseHas('price_groups', [
            'id' => $priceGroup->id,
            'name' => 'Temporada Alta Actualizada',
        ]);

        // Verificar que se crearon los precios
        $this->assertDatabaseHas('cabin_price_by_guests', [
            'cabin_id' => $cabin->id,
            'price_group_id' => $priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100.00,
        ]);
    }

    public function test_update_complete_saves_priority(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $priceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => 0,
        ]);

        $updateData = [
            'name' => 'Temporada con Prioridad',
            'priority' => 50,
            'cabins' => [
                [
                    'cabin_id' => $cabin->id,
                    'prices' => [
                        ['num_guests' => 2, 'price_per_night' => 100.00],
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/price-groups/{$priceGroup->id}/complete", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Temporada con Prioridad')
            ->assertJsonPath('data.priority', 50);

        $this->assertDatabaseHas('price_groups', [
            'id' => $priceGroup->id,
            'name' => 'Temporada con Prioridad',
            'priority' => 50,
        ]);
    }

    public function test_update_complete_returns_404_for_nonexistent_group(): void
    {
        // El test debe verificar que devuelve 404 cuando el grupo no existe
        // Pero la validación del request ocurre primero, así que necesitamos datos válidos
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/price-groups/99999/complete", [
                'name' => 'Test',
                'cabins' => [
                    [
                        'cabin_id' => $cabin->id,
                        'prices' => [
                            ['num_guests' => 2, 'price_per_night' => 100.00],
                        ]
                    ]
                ]
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Grupo de precio no encontrado');
    }

    public function test_can_store_price_group_complete(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);

        $storeData = [
            'name' => 'Temporada Completa Crear',
            'priority' => 30,
            'cabins' => [
                [
                    'cabin_id' => $cabin->id,
                    'prices' => [
                        ['num_guests' => 2, 'price_per_night' => 100.00],
                        ['num_guests' => 3, 'price_per_night' => 150.00],
                    ]
                ]
            ]
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/price-groups/complete", $storeData);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Temporada Completa Crear')
            ->assertJsonPath('data.priority', 30);

        $this->assertDatabaseHas('price_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Temporada Completa Crear',
            'priority' => 30,
        ]);

        // Verificar precios
        $this->assertDatabaseHas('cabin_price_by_guests', [
            'cabin_id' => $cabin->id,
            'num_guests' => 2,
            'price_per_night' => 100.00,
        ]);
    }
}
