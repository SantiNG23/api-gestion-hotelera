<?php

declare(strict_types=1);

namespace App\Http\Resources;

final class TenantAccessResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
        ];
    }
}
