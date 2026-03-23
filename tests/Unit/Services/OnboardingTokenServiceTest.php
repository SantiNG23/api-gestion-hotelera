<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Onboarding\OnboardingTokenService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingTokenServiceTest extends TestCase
{
    #[Test]
    public function it_generates_a_high_entropy_prefixed_token(): void
    {
        $service = app(OnboardingTokenService::class);

        $firstToken = $service->generatePlainTextToken();
        $secondToken = $service->generatePlainTextToken();

        $this->assertStringStartsWith('btp_live_', $firstToken);
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertSame(73, strlen($firstToken));
    }

    #[Test]
    public function it_hashes_and_matches_tokens_safely(): void
    {
        $service = app(OnboardingTokenService::class);
        $plainTextToken = $service->generatePlainTextToken();
        $hash = $service->hashToken($plainTextToken);

        $this->assertNotSame($plainTextToken, $hash);
        $this->assertTrue($service->matches($plainTextToken, $hash));
        $this->assertFalse($service->matches($plainTextToken.'-invalid', $hash));
    }
}
