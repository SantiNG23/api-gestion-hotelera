<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Cabin;
use App\Models\Feature;
use App\Models\Tenant;
use Tests\TestCase;

class CabinTest extends TestCase
{
    protected ?Tenant $localTenant = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localTenant = Tenant::factory()->create();
    }

    public function test_has_features_relationship(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        $features = Feature::factory()->count(3)->create(['tenant_id' => $this->localTenant->id]);

        $cabin->features()->attach($features);

        $this->assertCount(3, $cabin->features);
    }

    public function test_can_sync_features(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        $features = Feature::factory()->count(2)->create(['tenant_id' => $this->localTenant->id]);

        $cabin->features()->sync($features->pluck('id'));

        $this->assertCount(2, $cabin->fresh()->features);
    }

    public function test_soft_deletes(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);

        $cabin->delete();

        $this->assertSoftDeleted('cabins', ['id' => $cabin->id]);
    }

    public function test_casts_is_active_to_boolean(): void
    {
        $cabin = Cabin::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'is_active' => true,
        ]);

        $this->assertIsBool($cabin->is_active);
    }

    public function test_casts_capacity_to_integer(): void
    {
        $cabin = Cabin::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'capacity' => 4,
        ]);

        $this->assertIsInt($cabin->capacity);
    }
}

