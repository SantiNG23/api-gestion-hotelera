<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class TenantContextResolver
{
    public function resolveForRequest(Request $request): ?int
    {
        $tenantId = $request->user()?->tenant_id ?? Auth::guard('sanctum')->user()?->tenant_id;

        if ($tenantId !== null) {
            return (int) $tenantId;
        }

        if ($request->routeIs('auth.store', 'auth.login')) {
            return $this->resolveForPublicAuth($request->all());
        }

        return null;
    }

    public function resolveForPublicAuth(array $payload): ?int
    {
        if (isset($payload['tenant_id'])) {
            return null;
        }

        if (isset($payload['tenant_slug']) && is_string($payload['tenant_slug']) && $payload['tenant_slug'] !== '') {
            $tenantId = Tenant::query()
                ->where('slug', $payload['tenant_slug'])
                ->where('is_active', true)
                ->value('id');

            return $tenantId !== null ? (int) $tenantId : null;
        }

        $tenantCount = Tenant::query()->count();

        if ($tenantCount === 1) {
            return (int) Tenant::query()->value('id');
        }

        return null;
    }

    public function resolveForCommand(?int $tenantId): int
    {
        if ($tenantId === null || ! Tenant::query()->whereKey($tenantId)->exists()) {
            throw MissingTenantContextException::forCommand();
        }

        return $tenantId;
    }
}
