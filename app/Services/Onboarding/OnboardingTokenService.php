<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

class OnboardingTokenService
{
    public function generatePlainTextToken(): string
    {
        return 'btp_live_'.bin2hex(random_bytes(32));
    }

    public function hashToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    public function matches(string $plainTextToken, string $persistedHash): bool
    {
        return hash_equals($persistedHash, $this->hashToken($plainTextToken));
    }
}
