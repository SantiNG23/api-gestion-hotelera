<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Feature;
use Tests\TestCase;

class CabinApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_list_cabins(): void
    {
        Cabin::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/cabins');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_cabin(): void
    {
        $data = [
            'name' => 'Cabaña del Lago',
            'description' => 'Hermosa cabaña con vista al lago',
            'capacity' => 4,
            'is_active' => true,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/cabins', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Cabaña del Lago')
            ->assertJsonPath('data.capacity', 4);

        $this->assertDatabaseHas('cabins', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabaña del Lago',
        ]);
    }

    public function test_can_create_cabin_with_features(): void
    {
        $features = Feature::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $data = [
            'name' => 'Cabaña Premium',
            'capacity' => 6,
            'feature_ids' => $features->pluck('id')->toArray(),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/cabins', $data);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.features');
    }

    public function test_cannot_create_cabin_without_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/cabins', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'capacity']);
    }

    public function test_can_show_cabin_with_features(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $features = Feature::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        $cabin->features()->attach($features);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/cabins/{$cabin->id}");

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.id', $cabin->id);
        $response->assertJsonCount(2, 'data.features');
    }

    public function test_can_update_cabin(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/cabins/{$cabin->id}", [
                'name' => 'Cabaña Renovada',
                'capacity' => 8,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.name', 'Cabaña Renovada');
        $response->assertJsonPath('data.capacity', 8);
    }

    public function test_can_update_cabin_features(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $oldFeatures = Feature::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        $cabin->features()->attach($oldFeatures);

        $newFeatures = Feature::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/cabins/{$cabin->id}", [
                'feature_ids' => $newFeatures->pluck('id')->toArray(),
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonCount(3, 'data.features');
    }

    public function test_can_delete_cabin(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/cabins/{$cabin->id}");

        $this->assertApiResponse($response);
        $this->assertSoftDeleted('cabins', ['id' => $cabin->id]);
    }

    public function test_can_filter_by_capacity(): void
    {
        Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 2]);
        Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 4]);
        Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 6]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/cabins?min_capacity=4');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(2, 'data');
    }
}

