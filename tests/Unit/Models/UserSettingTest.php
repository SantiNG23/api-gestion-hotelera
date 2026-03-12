<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSetting;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserSettingTest extends TestCase
{
    #[Test]
    public function it_hides_user_settings_from_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        UserSetting::query()->withoutGlobalScope('tenant')->create([
            'user_id' => $userA->id,
            'tenant_id' => $tenantA->id,
        ]);

        $foreignSetting = UserSetting::query()->withoutGlobalScope('tenant')->create([
            'user_id' => $userB->id,
            'tenant_id' => $tenantB->id,
        ]);

        $this->setTenantContext($tenantA->id);

        $this->assertNull(UserSetting::find($foreignSetting->id));
        $this->assertCount(1, UserSetting::all());
    }

    #[Test]
    public function it_normalizes_mismatched_user_and_tenant_ids(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        $this->setTenantContext($tenantA->id);

        $setting = UserSetting::query()->withoutGlobalScope('tenant')->create([
            'user_id' => $userA->id,
            'tenant_id' => $tenantB->id,
        ]);

        $this->assertSame($tenantA->id, $setting->tenant_id);
    }
}
