<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\OnboardingInvitation;
use App\Services\Onboarding\OnboardingTokenService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingResolveTest extends TestCase
{
    #[Test]
    public function it_resolves_a_pending_invitation_without_authenticating_the_request(): void
    {
        $token = 'btp_live_valid_token';
        $invitation = $this->createInvitationForToken($token, [
            'email' => 'owner@cliente.com',
            'tenant_name_prefill' => 'Hotel Demo',
            'tenant_slug_prefill' => 'hotel-demo',
        ]);

        $response = $this->postJson('/api/v1/auth/onboarding/resolve', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertHeader('X-RateLimit-Limit', '10')
            ->assertJson([
                'success' => true,
                'message' => 'Invitacion valida.',
                'data' => [
                    'status' => 'pending',
                    'email' => 'owner@cliente.com',
                    'tenant_prefill' => [
                        'name' => 'Hotel Demo',
                        'slug' => 'hotel-demo',
                    ],
                ],
            ])
            ->assertJsonPath('data.expires_at', $invitation->expires_at->clone()->utc()->toIso8601String());

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_rejects_an_invalid_request_for_resolve(): void
    {
        $response = $this->postJson('/api/v1/auth/onboarding/resolve', [
            'email' => 'owner@cliente.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'invalid_request')
            ->assertJsonPath('errors.token.0', 'El token es obligatorio.');
    }

    #[Test]
    public function it_returns_token_invalid_when_the_invitation_does_not_exist(): void
    {
        $response = $this->postJson('/api/v1/auth/onboarding/resolve', [
            'token' => 'btp_live_missing_token',
        ]);

        $response->assertStatus(410)
            ->assertJsonPath('errors.code.0', 'token_invalid');
    }

    #[Test]
    public function it_returns_terminal_codes_for_expired_consumed_and_revoked_tokens(): void
    {
        $cases = [
            'token_expired' => OnboardingInvitation::factory()->expired()->create([
                'token_hash' => app(OnboardingTokenService::class)->hashToken('btp_live_expired_token'),
            ]),
            'token_consumed' => OnboardingInvitation::factory()->consumed()->create([
                'token_hash' => app(OnboardingTokenService::class)->hashToken('btp_live_consumed_token'),
            ]),
            'token_revoked' => OnboardingInvitation::factory()->revoked()->create([
                'token_hash' => app(OnboardingTokenService::class)->hashToken('btp_live_revoked_token'),
            ]),
        ];

        foreach ([
            'token_expired' => 'btp_live_expired_token',
            'token_consumed' => 'btp_live_consumed_token',
            'token_revoked' => 'btp_live_revoked_token',
        ] as $expectedCode => $token) {
            $response = $this->postJson('/api/v1/auth/onboarding/resolve', [
                'token' => $token,
            ]);

            $response->assertStatus(410)
                ->assertJsonPath('errors.code.0', $expectedCode);
        }

        $this->assertCount(3, $cases);
    }

    #[Test]
    public function it_applies_the_specific_onboarding_rate_limit(): void
    {
        $token = 'btp_live_rate_limited_token';
        $this->createInvitationForToken($token);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/onboarding/resolve', [
                'token' => $token,
            ])->assertOk();
        }

        $response = $this->postJson('/api/v1/auth/onboarding/resolve', [
            'token' => $token,
        ]);

        $response->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '10')
            ->assertJsonPath('errors.code.0', 'rate_limited');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInvitationForToken(string $token, array $attributes = []): OnboardingInvitation
    {
        return OnboardingInvitation::factory()->create(array_merge([
            'token_hash' => app(OnboardingTokenService::class)->hashToken($token),
        ], $attributes));
    }
}
