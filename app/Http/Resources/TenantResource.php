<?php

declare(strict_types=1);

namespace App\Http\Resources;

final class TenantResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
        ];
    }
}
