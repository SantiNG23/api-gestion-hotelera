<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Client;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_list_clients(): void
    {
        Client::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/clients');

        $this->assertPaginatedResponse($response);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(5, 'data');
    }

    public function test_can_create_client(): void
    {
        $data = [
            'name' => 'Juan Pérez',
            'dni' => '12345678',
            'age' => 35,
            'city' => 'Buenos Aires',
            'phone' => '1155667788',
            'email' => 'juan@test.com',
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/clients', $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Juan Pérez')
            ->assertJsonPath('data.dni', '12345678');

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $this->tenant->id,
            'dni' => '12345678',
        ]);
    }

    public function test_cannot_create_client_without_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/clients', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'dni']);
    }

    public function test_cannot_create_duplicate_dni_in_same_tenant(): void
    {
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dni' => '12345678',
        ]);

        $data = [
            'name' => 'Otro Cliente',
            'dni' => '12345678',
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/clients', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dni']);
    }

    public function test_can_show_client(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/clients/{$client->id}");

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.id', $client->id);
    }

    public function test_can_update_client(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $data = ['name' => 'Nombre Actualizado'];

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/clients/{$client->id}", $data);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.name', 'Nombre Actualizado');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Nombre Actualizado',
        ]);
    }

    public function test_can_delete_client(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/clients/{$client->id}");

        $this->assertApiResponse($response);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_can_search_client_by_dni(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dni' => '99887766',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/clients/dni/99887766');

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.id', $client->id);
    }

    public function test_search_by_dni_returns_404_when_not_found(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/clients/dni/nonexistent');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_clients(): void
    {
        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(401);
    }

    public function test_can_filter_clients_by_name(): void
    {
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Juan García']);
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'María López']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/clients?name=Juan');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data');
    }
}

