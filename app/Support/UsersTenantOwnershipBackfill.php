<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use RuntimeException;

class UsersTenantOwnershipBackfill
{
    public function backfillOrFail(): void
    {
        $orphanUsers = User::query()
            ->withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->get(['id']);

        if ($orphanUsers->isEmpty()) {
            return;
        }

        $tenantIds = Tenant::query()->pluck('id');

        if ($tenantIds->count() === 1) {
            User::query()
                ->withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->update(['tenant_id' => (int) $tenantIds->first()]);

            return;
        }

        throw new RuntimeException(
            'No se puede endurecer users.tenant_id: existen usuarios huerfanos sin tenant resoluble. IDs: '.
            $orphanUsers->pluck('id')->implode(', ')
        );
    }
}
