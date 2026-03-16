<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Exceptions\MissingTenantContextException;
use App\Tenancy\TenantContext;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    public function test_it_sets_and_clears_the_tenant_context(): void
    {
        $context = app(TenantContext::class);

        $context->set(10);

        $this->assertSame(10, $context->id());

        $context->clear();

        $this->assertNull($context->id());
    }

    public function test_it_requires_an_active_tenant_context(): void
    {
        $this->expectException(MissingTenantContextException::class);

        app(TenantContext::class)->requireId();
    }

    public function test_it_restores_the_previous_context_after_run(): void
    {
        $context = app(TenantContext::class);
        $context->set(5);

        $result = $context->run(9, function () use ($context): int {
            $this->assertSame(9, $context->id());

            return $context->requireId();
        });

        $this->assertSame(9, $result);
        $this->assertSame(5, $context->id());
    }
}
