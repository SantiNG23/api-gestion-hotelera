<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use App\Models\Tenant;
use App\Services\PublicQuote\PublicQuoteTokenService;
use Carbon\Carbon;
use Tests\TestCase;

class PublicQuoteApiTest extends TestCase
{
    private string $plainTextToken;

    private Cabin $cabin;

    private Tenant $otherTenant;

    private Cabin $otherTenantCabin;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenService = app(PublicQuoteTokenService::class);
        $this->plainTextToken = $tokenService->generatePlainTextToken();

        $this->tenant = $this->createTenant([
            'slug' => 'mirador-publico',
            'is_active' => true,
            'public_quote_token_hash' => $tokenService->hashToken($this->plainTextToken),
        ]);

        $this->runInTenantContext($this->tenant->id, function (): void {
            $this->cabin = Cabin::factory()->create([
                'tenant_id' => $this->tenant->id,
                'capacity' => 4,
            ]);

            $defaultPriceGroup = PriceGroup::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Tarifa Base',
                'is_default' => true,
            ]);

            CabinPriceByGuests::factory()->create([
                'tenant_id' => $this->tenant->id,
                'cabin_id' => $this->cabin->id,
                'price_group_id' => $defaultPriceGroup->id,
                'num_guests' => 2,
                'price_per_night' => 100,
            ]);
        });

        $this->otherTenant = Tenant::factory()->create([
            'slug' => 'mirador-ajeno',
        ]);

        $this->runInTenantContext($this->otherTenant->id, function (): void {
            $this->otherTenantCabin = Cabin::factory()->create([
                'tenant_id' => $this->otherTenant->id,
            ]);
        });
    }

    public function test_public_quote_returns_quote_for_active_tenant_with_valid_token(): void
    {
        $response = $this->withHeaders($this->publicHeaders())
            ->postJson($this->publicQuoteUrl($this->tenant->slug), [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $this->assertApiResponse($response);
        $response
            ->assertJsonPath('data.cabin_id', $this->cabin->id)
            ->assertJsonPath('data.check_in', Carbon::tomorrow()->format('Y-m-d'))
            ->assertJsonPath('data.check_out', Carbon::tomorrow()->addDays(3)->format('Y-m-d'))
            ->assertJsonPath('data.nights', 3)
            ->assertJsonPath('data.total', 300.0)
            ->assertJsonPath('data.deposit', 150.0)
            ->assertJsonPath('data.balance', 150.0);
    }

    public function test_public_quote_rejects_invalid_token(): void
    {
        $response = $this->withHeaders($this->publicHeaders('token-invalido'))
            ->postJson($this->publicQuoteUrl($this->tenant->slug), [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $response->assertStatus(401)
            ->assertJsonPath('errors.code.0', 'invalid_public_quote_token');
    }

    public function test_public_quote_rejects_inactive_or_missing_tenant(): void
    {
        $inactiveTenant = Tenant::factory()->inactive()->create([
            'slug' => 'mirador-inactivo',
            'public_quote_token_hash' => app(PublicQuoteTokenService::class)->hashToken($this->plainTextToken),
        ]);

        foreach ([$inactiveTenant->slug, 'tenant-inexistente'] as $tenantSlug) {
            $response = $this->withHeaders($this->publicHeaders())
                ->postJson($this->publicQuoteUrl($tenantSlug), [
                    'cabin_id' => $this->cabin->id,
                    'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                    'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                    'num_guests' => 2,
                ]);

            $response->assertStatus(404)
                ->assertJsonPath('errors.code.0', 'tenant_not_found');
        }
    }

    public function test_public_quote_rejects_cabin_from_other_tenant(): void
    {
        $response = $this->withHeaders($this->publicHeaders())
            ->postJson($this->publicQuoteUrl($this->tenant->slug), [
                'cabin_id' => $this->otherTenantCabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cabin_id']);
    }

    public function test_public_quote_does_not_return_is_available(): void
    {
        $response = $this->withHeaders($this->publicHeaders())
            ->postJson($this->publicQuoteUrl($this->tenant->slug), [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $this->assertApiResponse($response);
        $this->assertArrayNotHasKey('is_available', $response->json('data'));
    }

    public function test_public_quote_rate_limit_applies(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withHeaders($this->publicHeaders())
                ->postJson($this->publicQuoteUrl($this->tenant->slug), [
                    'cabin_id' => $this->cabin->id,
                    'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                    'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                    'num_guests' => 2,
                ])
                ->assertOk();
        }

        $response = $this->withHeaders($this->publicHeaders())
            ->postJson($this->publicQuoteUrl($this->tenant->slug), [
                'cabin_id' => $this->cabin->id,
                'check_in_date' => Carbon::tomorrow()->format('Y-m-d'),
                'check_out_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
                'num_guests' => 2,
            ]);

        $response->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '10')
            ->assertJsonPath('errors.code.0', 'rate_limited');
    }

    private function publicHeaders(?string $token = null): array
    {
        return [
            'Accept' => 'application/json',
            PublicQuoteTokenService::HEADER_NAME => $token ?? $this->plainTextToken,
        ];
    }

    private function publicQuoteUrl(string $tenantSlug): string
    {
        return '/api/v1/public/tenants/'.$tenantSlug.'/quote';
    }
}
