<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantContextResolver $tenantContextResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $previousTenantId = $this->tenantContext->id();
        $resolvedTenantId = $this->tenantContextResolver->resolveForRequest($request);

        if ($resolvedTenantId !== null) {
            $this->tenantContext->set($resolvedTenantId);
        }

        try {
            return $next($request);
        } finally {
            if ($previousTenantId === null) {
                $this->tenantContext->clear();
            } else {
                $this->tenantContext->set($previousTenantId);
            }
        }
    }
}
