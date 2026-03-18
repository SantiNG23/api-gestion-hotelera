<?php

declare(strict_types=1);

namespace App\Http\Resources;

class AuthResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->resource['token'],
            'user' => (new UserResource($this->resource['user']))->resolve(),
            'tenant' => (new TenantResource($this->resource['tenant']))->resolve(),
        ];
    }
}
