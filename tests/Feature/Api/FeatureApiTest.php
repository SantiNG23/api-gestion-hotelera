<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Feature;
use Tests\TestCase;

class FeatureApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_list_features(): void
    {
        Feature::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/features');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_feature(): void
    {
        $data = [
            'name' => 'Pileta',
            'icon' => 'pool',
            'is_active' => true,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/features', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Pileta');

        $this->assertDatabaseHas('features', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Pileta',
        ]);
    }

    public function test_cannot_create_feature_without_name(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/features', ['icon' => 'test']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_feature(): void
    {
        $feature = Feature::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/features/{$feature->id}", [
                'name' => 'WiFi Premium',
                'is_active' => false,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.name', 'WiFi Premium');
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_can_delete_feature(): void
    {
        $feature = Feature::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/features/{$feature->id}");

        $this->assertApiResponse($response);
        $this->assertSoftDeleted('features', ['id' => $feature->id]);
    }

    public function test_can_filter_by_is_active(): void
    {
        Feature::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Feature::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/features?is_active=true');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(2, 'data');
    }
}

