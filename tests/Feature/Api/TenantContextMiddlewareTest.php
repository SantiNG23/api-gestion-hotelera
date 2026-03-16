<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PriceGroup;
use App\Models\Tenant;
use Tests\TestCase;

class TenantContextMiddlewareTest extends TestCase
{
    public function test_it_resolves_tenant_context_from_authenticated_api_request(): void
    {
        $this->createAuthenticatedUser();
        $this->actingAs($this->user, 'sanctum');

        PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Grupo actual',
        ]);

        $otherTenant = Tenant::factory()->create();
        $this->runInTenantContext($otherTenant->id, function () use ($otherTenant): void {
            PriceGroup::factory()->create([
                'tenant_id' => $otherTenant->id,
                'name' => 'Grupo ajeno',
            ]);
        });

        $this->setTenantContext(null);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/price-groups');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Grupo actual');
    }
}
