<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    #[Test]
    public function it_can_validate_user_credentials()
    {
        $tenant = Tenant::factory()->create();

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $result = $this->authService->validateCredentials('test@example.com', 'password123', $tenant->id);
        $this->assertTrue($result);

        $result = $this->authService->validateCredentials('test@example.com', 'wrongpassword', $tenant->id);
        $this->assertFalse($result);
    }

    #[Test]
    public function it_discovers_active_tenants_for_an_email(): void
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
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'test@example.com',
        ]);

        $result = $this->authService->discover('test@example.com');

        $this->assertSame('multi_tenant', $result['mode']);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertCount(2, $result['tenants']);
        $this->assertSame('mirador-centro', $result['tenants'][0]->slug);
        $this->assertSame('mirador-norte', $result['tenants'][1]->slug);
    }

    #[Test]
    public function it_logs_in_with_email_password_and_tenant_slug(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'mirador-centro',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = $this->authService->login([
            'email' => 'test@example.com',
            'password' => 'password123',
            'tenant_slug' => 'mirador-centro',
        ]);

        $this->assertSame($user->id, $payload['user']->id);
        $this->assertSame($tenant->id, $payload['tenant']->id);
        $this->assertIsString($payload['token']);
        $this->assertStringContainsString('|', $payload['token']);
    }

    #[Test]
    public function it_throws_a_functional_error_for_inactive_tenants(): void
    {
        $tenant = Tenant::factory()->inactive()->create([
            'slug' => 'mirador-cerrado',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        try {
            $this->authService->login([
                'email' => 'test@example.com',
                'password' => 'password123',
                'tenant_slug' => 'mirador-cerrado',
            ]);

            $this->fail('Se esperaba ValidationException para un tenant inactivo.');
        } catch (ValidationException $exception) {
            $this->assertSame(['inactive_tenant'], $exception->errors()['code']);
            $this->assertSame(['La cuenta seleccionada no esta disponible.'], $exception->errors()['tenant_slug']);
        }
    }

    #[Test]
    public function it_can_revoke_all_tokens()
    {
        $user = User::factory()->create();
        $user->createToken('test-token-1');
        $user->createToken('test-token-2');

        $this->authService->revokeAllTokens($user);

        $this->assertCount(0, $user->tokens);
    }

    #[Test]
    public function it_can_create_api_token()
    {
        $user = User::factory()->create();
        $token = $this->authService->createApiToken($user, 'test-token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('|', $token);
        $this->assertCount(1, $user->tokens);
    }

    #[Test]
    public function it_can_bootstrap_the_authenticated_user_with_tenant_loaded(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $bootstrappedUser = $this->authService->bootstrap($user);

        $this->assertTrue($bootstrappedUser->relationLoaded('tenant'));
        $this->assertSame($tenant->id, $bootstrappedUser->tenant->id);
    }
}
