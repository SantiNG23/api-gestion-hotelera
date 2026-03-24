<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\OnboardingInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Onboarding\CompleteOnboardingService;
use App\Services\Onboarding\OnboardingTokenService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Mockery;
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
    public function it_rejects_tokens_longer_than_255_characters_for_complete(): void
    {
        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload(str_repeat('a', 256)));

        $response->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'invalid_request')
            ->assertJsonPath('errors.token.0', 'El token no puede tener mas de 255 caracteres.');
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
        Exceptions::fake();

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
        Exceptions::assertReported(RuntimeException::class);
    }

    #[Test]
    public function it_applies_the_specific_onboarding_rate_limit_to_complete(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $token = 'btp_live_complete_rate_limit_'.$attempt;
            $this->createInvitationForToken($token, [
                'email' => 'owner'.$attempt.'@cliente.com',
            ]);

            $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token, [
                'tenant' => [
                    'slug' => 'hotel-demo-'.$attempt,
                ],
            ]))->assertCreated();
        }

        $token = 'btp_live_complete_rate_limit_blocked';
        $this->createInvitationForToken($token, [
            'email' => 'blocked@cliente.com',
        ]);

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token, [
            'tenant' => [
                'slug' => 'hotel-demo-blocked',
            ],
        ]));

        $response->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '10')
            ->assertJsonPath('errors.code.0', 'rate_limited');

        $this->assertNull($this->findInvitationByToken($token)?->consumed_at);
    }

    #[Test]
    public function it_can_complete_onboarding_without_marking_email_as_verified_and_queue_the_welcome_mail(): void
    {
        config()->set('onboarding.completion.mark_email_as_verified', false);
        config()->set('onboarding.completion.send_welcome_mail', true);

        $token = 'btp_live_complete_mail_policy';
        $this->createInvitationForToken($token, [
            'email' => 'owner@cliente.com',
        ]);

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token, [
            'tenant' => [
                'slug' => 'hotel-demo-mail-policy',
            ],
        ]));

        $response->assertCreated();

        $owner = User::query()->sole();

        $this->assertNull($owner->email_verified_at);
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);
        Mail::assertQueued(\App\Mail\WelcomeUserMail::class);
    }

    #[Test]
    public function it_does_not_sanitize_user_password_fields_during_complete(): void
    {
        $token = 'btp_live_password_whitespace';
        $password = '  Secret123!  ';
        $tenant = Tenant::factory()->create([
            'slug' => 'hotel-demo-password',
        ]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@cliente.com',
            'role' => User::ROLE_OWNER,
        ]);

        $this->createInvitationForToken($token, [
            'email' => 'owner@cliente.com',
        ]);

        $capturedPayload = null;
        $service = Mockery::mock(CompleteOnboardingService::class);
        $service->shouldReceive('complete')
            ->once()
            ->withArgs(function (array $payload) use (&$capturedPayload): bool {
                $capturedPayload = $payload;

                return true;
            })
            ->andReturn([
                'token' => 'plain-text-token',
                'user' => $owner,
                'tenant' => $tenant,
            ]);

        $this->app->instance(CompleteOnboardingService::class, $service);

        $response = $this->postJson('/api/v1/auth/onboarding/complete', $this->validPayload($token, [
            'tenant' => [
                'slug' => 'hotel-demo-password',
            ],
            'user' => [
                'password' => $password,
                'password_confirmation' => $password,
            ],
        ]));

        $response->assertCreated();

        $this->assertSame($token, $capturedPayload['token']);
        $this->assertSame($password, $capturedPayload['user']['password']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $token, array $overrides = []): array
    {
        return array_replace_recursive([
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
        ], $overrides);
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

    private function findInvitationByToken(string $token): ?OnboardingInvitation
    {
        return OnboardingInvitation::query()
            ->where('token_hash', app(OnboardingTokenService::class)->hashToken($token))
            ->first();
    }
}
