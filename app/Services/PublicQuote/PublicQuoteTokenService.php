<?php

declare(strict_types=1);

namespace App\Services\PublicQuote;

class PublicQuoteTokenService
{
    public const HEADER_NAME = 'X-Public-Quote-Token';

    public function generatePlainTextToken(): string
    {
        return 'pqt_live_'.bin2hex(random_bytes(32));
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
