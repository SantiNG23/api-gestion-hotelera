<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\OnboardingInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Onboarding\CompleteOnboardingService;
use App\Services\Onboarding\OnboardingTokenService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OnboardingCompleteTest extends TestCase
{
    #[Test]
    public function it_completes_onboarding_with_a_transactional_auth_response(): void
    {
        config()->set('onboarding.completion.mark_email_as_verified', true);
        config()->set('onboarding.completion.send_welcome_mail', false);

        $token = 'btp_live_complete_success';
        $invitation = $this->createInvitationForToken($token, [
            'email' => 'owner@cliente.com',
        ]);

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token));

        $response->assertCreated()
            ->assertHeader('X-RateLimit-Limit', '10')
            ->assertJson([
                'success' => true,
                'message' => 'Onboarding completado exitosamente.',
                'data' => [
                    'user' => [
                        'name' => 'Juan Perez',
                        'email' => 'owner@cliente.com',
                    ],
                    'tenant' => [
                        'name' => 'Hotel Demo',
                        'slug' => 'hotel-demo',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                    'tenant' => ['id', 'slug', 'name'],
                ],
            ]);

        $tenantId = (int) $response->json('data.tenant.id');
        $userId = (int) $response->json('data.user.id');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'name' => 'Hotel Demo',
            'slug' => 'hotel-demo',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'tenant_id' => $tenantId,
            'email' => 'owner@cliente.com',
            'role' => 'owner',
        ]);
        $this->assertNotNull(User::query()->findOrFail($userId)->email_verified_at);
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'locale' => 'es_AR',
            'timezone' => 'America/Argentina/Buenos_Aires',
        ]);
        $this->assertNotNull($invitation->fresh()->consumed_at);
        $this->assertNotNull($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Mail::assertNothingQueued();
    }

    #[Test]
    public function it_rejects_prohibited_payload_fields_for_complete(): void
    {
        $response = $this->postJson('/api/v1/auth/onboarding/complete', [
            'token' => 'btp_live_payload_validation',
            'tenant_id' => 99,
            'tenant' => [
                'name' => 'Hotel Demo',
                'slug' => 'HOTEL-DEMO',
            ],
            'user' => [
                'name' => 'Juan Perez',
                'email' => 'owner@cliente.com',
                'password' => 'Secret123!',
                'password_confirmation' => 'Secret123!',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'invalid_request')
            ->assertJsonValidationErrors(['tenant_id', 'user.email']);
    }

    #[Test]
    public function it_returns_terminal_codes_for_complete_when_the_token_is_not_usable(): void
    {
        $this->createInvitationForToken('btp_live_complete_expired', [
            'expires_at' => now()->subMinute(),
        ]);
        $this->createInvitationForToken('btp_live_complete_consumed', [
            'consumed_at' => now()->subMinute(),
        ]);
        $this->createInvitationForToken('btp_live_complete_revoked', [
            'revoked_at' => now()->subMinute(),
        ]);

        foreach ([
            'btp_live_missing_complete' => 'token_invalid',
            'btp_live_complete_expired' => 'token_expired',
            'btp_live_complete_consumed' => 'token_consumed',
            'btp_live_complete_revoked' => 'token_revoked',
        ] as $token => $expectedCode) {
            $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token));

            $response->assertStatus(410)
                ->assertJsonPath('errors.code.0', $expectedCode);
        }
    }

    #[Test]
    public function it_returns_tenant_slug_taken_without_consuming_the_invitation(): void
    {
        $token = 'btp_live_slug_taken';
        $invitation = $this->createInvitationForToken($token);
        Tenant::factory()->create([
            'slug' => 'hotel-demo',
        ]);

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token));

        $response->assertStatus(409)
            ->assertJsonPath('errors.code.0', 'tenant_slug_taken')
            ->assertJsonFragment([
                'tenant.slug' => ['El slug seleccionado no esta disponible.'],
            ]);

        $this->assertNull($invitation->fresh()->consumed_at);
        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function it_cannot_reuse_the_same_token_twice(): void
    {
        $token = 'btp_live_single_use';
        $this->createInvitationForToken($token, [
            'email' => 'owner@cliente.com',
        ]);

        $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token))
            ->assertCreated();

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token));

        $response->assertStatus(410)
            ->assertJsonPath('errors.code.0', 'token_consumed');

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_settings', 1);
    }

    #[Test]
    public function it_rolls_back_and_returns_onboarding_conflict_when_user_creation_hits_a_domain_conflict(): void
    {
        $token = 'btp_live_conflict';
        $invitation = $this->createInvitationForToken($token);

        $this->app->instance(CompleteOnboardingService::class, new class(app(AuthService::class), app(OnboardingTokenService::class)) extends CompleteOnboardingService
        {
            public function createOwnerForTenant(Tenant $tenant, array $userData): User
            {
                throw ValidationException::withMessages([
                    'email' => ['Conflicto forzado para testear rollback.'],
                ]);
            }
        });

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token));

        $response->assertStatus(409)
            ->assertJsonPath('errors.code.0', 'onboarding_conflict');

        $this->assertNull($invitation->fresh()->consumed_at);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_settings', 0);
    }

    #[Test]
    public function it_rolls_back_and_returns_onboarding_unavailable_when_a_critical_failure_happens(): void
    {
        $token = 'btp_live_unavailable';
        $invitation = $this->createInvitationForToken($token);

        $this->app->instance(CompleteOnboardingService::class, new class(app(AuthService::class), app(OnboardingTokenService::class)) extends CompleteOnboardingService
        {
            protected function createOwnerSettings(User $owner): \App\Models\UserSetting
            {
                throw new RuntimeException('boom');
            }
        });

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token));

        $response->assertStatus(503)
            ->assertJsonPath('errors.code.0', 'onboarding_unavailable');

        $this->assertNull($invitation->fresh()->consumed_at);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_settings', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $token): array
    {
        return [
            'token' => $token,
            'tenant' => [
                'name' => 'Hotel Demo',
                'slug' => 'HOTEL-DEMO',
            ],
            'user' => [
                'name' => 'Juan Perez',
                'password' => 'Secret123!',
                'password_confirmation' => 'Secret123!',
            ],
        ];
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
