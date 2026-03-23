<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OnboardingInvitation;

class OnboardingInvitationResource extends ApiResource
{
    /**
     * @param  OnboardingInvitation  $resource
     */
    public function toArray($request): array
    {
        return [
            'status' => $this->status,
            'email' => $this->email,
            'expires_at' => $this->expires_at?->clone()->utc()->toIso8601String(),
            'tenant_prefill' => $this->tenantPrefill(),
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function tenantPrefill(): ?array
    {
        if ($this->tenant_name_prefill === null && $this->tenant_slug_prefill === null) {
            return null;
        }

        return [
            'name' => $this->tenant_name_prefill,
            'slug' => $this->tenant_slug_prefill,
        ];
    }
}
