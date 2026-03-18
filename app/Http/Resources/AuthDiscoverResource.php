<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Support\Collection;

final class AuthDiscoverResource extends ApiResource
{
    public function toArray($request): array
    {
        /** @var Collection<int, mixed> $tenants */
        $tenants = collect($this->resource['tenants'] ?? []);

        return [
            'mode' => $this->resource['mode'],
            'email' => $this->resource['email'],
            'tenants' => TenantAccessResource::collection($tenants)->resolve(),
        ];
    }
}
