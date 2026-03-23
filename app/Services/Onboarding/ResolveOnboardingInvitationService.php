<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Exceptions\OnboardingException;
use App\Models\OnboardingInvitation;

class ResolveOnboardingInvitationService
{
    public function __construct(
        private readonly OnboardingTokenService $tokenService,
    ) {}

    public function resolve(string $plainTextToken): OnboardingInvitation
    {
        $invitation = OnboardingInvitation::query()
            ->where('token_hash', $this->tokenService->hashToken($plainTextToken))
            ->first();

        if (! $invitation) {
            throw OnboardingException::tokenInvalid();
        }

        if ($invitation->isRevoked()) {
            throw OnboardingException::tokenRevoked();
        }

        if ($invitation->isConsumed()) {
            throw OnboardingException::tokenConsumed();
        }

        if ($invitation->isExpired()) {
            throw OnboardingException::tokenExpired();
        }

        return $invitation;
    }
}
