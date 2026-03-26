<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tenant;
use App\Services\PublicQuote\PublicQuoteTokenService;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IssuePublicQuoteTokenCommandTest extends TestCase
{
    #[Test]
    public function it_generates_a_public_quote_token_for_an_active_tenant_and_persists_only_the_hash(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'mirador-activo',
            'public_quote_token_hash' => null,
        ]);

        $exitCode = Artisan::call('quote:issue-public-token', [
            'tenant-slug' => $tenant->slug,
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $plainTextToken = $this->extractIssuedToken($output);
        $persistedHash = $tenant->fresh()->public_quote_token_hash;

        $this->assertNotNull($persistedHash);
        $this->assertNotSame($plainTextToken, $persistedHash);
        $this->assertSame(app(PublicQuoteTokenService::class)->hashToken($plainTextToken), $persistedHash);
        $this->assertStringContainsString('Se persistio solo el hash', $output);
    }

    #[Test]
    public function it_regenerates_the_public_quote_token_and_invalidates_the_previous_one(): void
    {
        $tokenService = app(PublicQuoteTokenService::class);
        $previousPlainTextToken = $tokenService->generatePlainTextToken();

        $tenant = Tenant::factory()->create([
            'slug' => 'mirador-rotacion',
            'public_quote_token_hash' => $tokenService->hashToken($previousPlainTextToken),
        ]);

        $exitCode = Artisan::call('quote:issue-public-token', [
            'tenant-slug' => $tenant->slug,
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $newPlainTextToken = $this->extractIssuedToken($output);
        $persistedHash = $tenant->fresh()->public_quote_token_hash;

        $this->assertNotNull($persistedHash);
        $this->assertFalse($tokenService->matches($previousPlainTextToken, $persistedHash));
        $this->assertTrue($tokenService->matches($newPlainTextToken, $persistedHash));
        $this->assertStringContainsString('token anterior quedo invalidado', $output);
    }

    #[Test]
    public function it_fails_for_a_missing_tenant(): void
    {
        $exitCode = Artisan::call('quote:issue-public-token', [
            'tenant-slug' => 'tenant-inexistente',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No se encontro un tenant activo con slug [tenant-inexistente].', Artisan::output());
    }

    #[Test]
    public function it_fails_for_an_inactive_tenant(): void
    {
        $tenant = Tenant::factory()->inactive()->create([
            'slug' => 'tenant-inactivo',
            'public_quote_token_hash' => null,
        ]);

        $exitCode = Artisan::call('quote:issue-public-token', [
            'tenant-slug' => $tenant->slug,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No se encontro un tenant activo con slug [tenant-inactivo].', Artisan::output());
        $this->assertNull($tenant->fresh()->public_quote_token_hash);
    }

    private function extractIssuedToken(string $output): string
    {
        $matches = [];

        $this->assertSame(1, preg_match('/Token publico de cotizacion \(se muestra una sola vez\):\s*(pqt_live_[a-f0-9]{64})/', $output, $matches));

        return $matches[1];
    }
}
