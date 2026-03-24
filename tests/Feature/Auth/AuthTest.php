<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\OnboardingInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\OnboardingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_discovers_not_found_when_email_has_no_active_accesses(): void
    {
        Tenant::factory()->inactive()->create();

        $response = $this->postJson('/api/v1/auth/discover', [
            'email' => 'missing@miradordeluz.test',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'No se encontraron accesos para ese correo.',
                'data' => [
                    'mode' => 'not_found',
                    'email' => 'missing@miradordeluz.test',
                    'tenants' => [],
                ],
            ]);
    }

    #[Test]
    public function it_discovers_a_single_active_tenant_for_an_email(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Mirador Centro',
            'slug' => 'mirador-centro',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'operador@empresa.com',
        ]);

        $response = $this->postJson('/api/v1/auth/discover', [
            'email' => 'OPERADOR@EMPRESA.COM',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Acceso encontrado.',
                'data' => [
                    'mode' => 'single_tenant',
                    'email' => 'operador@empresa.com',
                    'tenants' => [
                        [
                            'slug' => 'mirador-centro',
                            'name' => 'Mirador Centro',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_discovers_multiple_active_tenants_for_the_same_email(): void
    {
        $tenantA = Tenant::factory()->create([
            'name' => 'Mirador Centro',
            'slug' => 'mirador-centro',
        ]);

        $tenantB = Tenant::factory()->create([
            'name' => 'Mirador Norte',
            'slug' => 'mirador-norte',
        ]);

        User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'operador@empresa.com',
        ]);

        User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'operador@empresa.com',
        ]);

        $response = $this->postJson('/api/v1/auth/discover', [
            'email' => 'operador@empresa.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Selecciona un tenant para continuar.',
                'data' => [
                    'mode' => 'multi_tenant',
                    'email' => 'operador@empresa.com',
                    'tenants' => [
                        [
                            'slug' => 'mirador-centro',
                            'name' => 'Mirador Centro',
                        ],
                        [
                            'slug' => 'mirador-norte',
                            'name' => 'Mirador Norte',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_does_not_contaminate_discover_with_pending_onboarding_invitations(): void
    {
        OnboardingInvitation::factory()->create([
            'email' => 'owner@cliente.com',
            'token_hash' => app(OnboardingTokenService::class)->hashToken('btp_live_pending_discover'),
        ]);

        $response = $this->postJson('/api/v1/auth/discover', [
            'email' => 'owner@cliente.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mode', 'not_found')
            ->assertJsonPath('data.tenants', []);
    }

    #[Test]
    public function it_logs_in_with_an_explicit_tenant_slug(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Mirador Centro',
            'slug' => 'mirador-centro',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operador Centro',
            'email' => 'operador@empresa.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'OPERADOR@EMPRESA.COM',
            'password' => 'Secret123!',
            'tenant_slug' => 'MIRADOR-CENTRO',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Usuario autenticado exitosamente',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Operador Centro',
                        'email' => 'operador@empresa.com',
                    ],
                    'tenant' => [
                        'id' => $tenant->id,
                        'slug' => 'mirador-centro',
                        'name' => 'Mirador Centro',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email'],
                    'tenant' => ['id', 'slug', 'name'],
                ],
            ]);

        $this->assertStringContainsString('|', $response->json('data.token'));
    }

    #[Test]
    public function it_allows_the_owner_created_by_onboarding_to_login_through_legacy_auth(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Hotel Demo',
            'slug' => 'hotel-demo',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Juan Perez',
            'email' => 'owner@cliente.com',
            'password' => Hash::make('Secret123!'),
            'role' => User::ROLE_OWNER,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@cliente.com',
            'password' => 'Secret123!',
            'tenant_slug' => 'hotel-demo',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'owner@cliente.com')
            ->assertJsonPath('data.tenant.slug', 'hotel-demo');
    }

    #[Test]
    public function it_rejects_login_without_tenant_slug(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operador@empresa.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.tenant_slug.0', 'Selecciona una cuenta para continuar.');
    }

    #[Test]
    public function it_rejects_tenant_id_from_the_client(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operador@empresa.com',
            'password' => 'Secret123!',
            'tenant_slug' => 'mirador-centro',
            'tenant_id' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id']);
    }

    #[Test]
    public function it_returns_invalid_credentials_when_email_and_tenant_do_not_match(): void
    {
        $tenantA = Tenant::factory()->create([
            'slug' => 'mirador-centro',
        ]);

        Tenant::factory()->create([
            'slug' => 'mirador-norte',
        ]);

        User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'operador@empresa.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operador@empresa.com',
            'password' => 'Secret123!',
            'tenant_slug' => 'mirador-norte',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'invalid_credentials')
            ->assertJsonPath('errors.email.0', 'Las credenciales proporcionadas son incorrectas.');
    }

    #[Test]
    public function it_rejects_inactive_tenants_during_login(): void
    {
        $tenant = Tenant::factory()->inactive()->create([
            'slug' => 'mirador-cerrado',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'operador@empresa.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'operador@empresa.com',
            'password' => 'Secret123!',
            'tenant_slug' => 'mirador-cerrado',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'inactive_tenant')
            ->assertJsonPath('errors.tenant_slug.0', 'La cuenta seleccionada no esta disponible.');
    }

    #[Test]
    public function it_bootstraps_the_authenticated_user_with_current_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Mirador Centro',
            'slug' => 'mirador-centro',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operador Centro',
            'email' => 'operador@empresa.com',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Usuario obtenido exitosamente',
                'data' => [
                    'id' => $user->id,
                    'name' => 'Operador Centro',
                    'email' => 'operador@empresa.com',
                    'tenant' => [
                        'id' => $tenant->id,
                        'slug' => 'mirador-centro',
                        'name' => 'Mirador Centro',
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.token');
    }

    #[Test]
    public function it_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/auth');

        $response->assertOk()
            ->assertJson([
                'message' => 'Sesión cerrada exitosamente',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
