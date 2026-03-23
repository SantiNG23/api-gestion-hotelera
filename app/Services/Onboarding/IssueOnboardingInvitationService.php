<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Mail\OnboardingInvitationMail;
use App\Models\OnboardingInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class IssueOnboardingInvitationService
{
    public function __construct(
        private readonly OnboardingTokenService $tokenService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function issue(
        string $email,
        ?string $tenantNamePrefill = null,
        ?string $tenantSlugPrefill = null,
        ?int $createdBy = null,
        ?array $meta = null,
        ?int $expiresInHours = null,
    ): OnboardingInvitation {
        $plainTextToken = $this->tokenService->generatePlainTextToken();

        $invitation = OnboardingInvitation::query()->create([
            'email' => Str::lower(trim($email)),
            'token_hash' => $this->tokenService->hashToken($plainTextToken),
            'expires_at' => now()->addHours($expiresInHours ?? config('onboarding.invitation.expires_in_hours')),
            'tenant_name_prefill' => $this->normalizeNullableString($tenantNamePrefill),
            'tenant_slug_prefill' => $this->normalizeSlug($tenantSlugPrefill),
            'created_by' => $createdBy,
            'meta' => $meta,
        ]);

        Mail::to($invitation->email)->send(new OnboardingInvitationMail($invitation, $plainTextToken));

        return $invitation;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeSlug(?string $value): ?string
    {
        $normalized = $this->normalizeNullableString($value);

        if ($normalized === null) {
            return null;
        }

        return Str::slug($normalized);
    }
}
