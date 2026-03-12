<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Support\UsersTenantOwnershipBackfill;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class UsersTenantOwnershipBackfillTest extends TestCase
{
    #[Test]
    public function it_backfills_orphan_users_when_there_is_only_one_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->change();
        });

        User::query()->withoutGlobalScopes()->whereKey($user->id)->update(['tenant_id' => null]);

        app(UsersTenantOwnershipBackfill::class)->backfillOrFail();

        $this->assertSame(
            $tenant->id,
            User::query()->withoutGlobalScopes()->whereKey($user->id)->value('tenant_id')
        );
    }

    #[Test]
    public function it_fails_when_orphan_users_cannot_be_resolved(): void
    {
        Tenant::factory()->count(2)->create();
        $user = User::factory()->create();

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->change();
        });

        User::query()->withoutGlobalScopes()->whereKey($user->id)->update(['tenant_id' => null]);

        $this->expectException(RuntimeException::class);

        app(UsersTenantOwnershipBackfill::class)->backfillOrFail();
    }
}
