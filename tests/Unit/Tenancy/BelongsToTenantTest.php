<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Client;
use App\Models\Tenant;
use Tests\TestCase;

class BelongsToTenantTest extends TestCase
{
    public function test_queries_fail_closed_without_tenant_context(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = Tenant::factory()->create();

        $this->runInTenantContext($tenantA->id, function () use ($tenantA): void {
            Client::factory()->create([
                'tenant_id' => $tenantA->id,
                'dni' => '11111111',
            ]);
        });

        $this->runInTenantContext($tenantB->id, function () use ($tenantB): void {
            Client::factory()->create([
                'tenant_id' => $tenantB->id,
                'dni' => '22222222',
            ]);
        });

        $this->setTenantContext(null);

        $this->assertSame(0, Client::query()->count());
    }

    public function test_queries_only_return_rows_for_the_active_tenant_context(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = Tenant::factory()->create();

        $this->runInTenantContext($tenantA->id, function () use ($tenantA): void {
            Client::factory()->create([
                'tenant_id' => $tenantA->id,
                'dni' => '44444444',
            ]);
        });

        $this->runInTenantContext($tenantB->id, function () use ($tenantB): void {
            Client::factory()->create([
                'tenant_id' => $tenantB->id,
                'dni' => '55555555',
            ]);
        });

        $this->setTenantContext($tenantA->id);

        $this->assertSame(1, Client::query()->count());
        $this->assertSame($tenantA->id, Client::query()->firstOrFail()->tenant_id);
    }
}
