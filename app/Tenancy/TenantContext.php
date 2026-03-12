<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Exceptions\MissingTenantContextException;

final class TenantContext
{
    private ?int $tenantId = null;

    public function set(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function requireId(): int
    {
        return $this->tenantId ?? throw MissingTenantContextException::forOperation();
    }

    public function run(int $tenantId, callable $callback): mixed
    {
        $previousTenantId = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previousTenantId;
        }
    }
}
