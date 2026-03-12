<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;
    use WithFaker;

    protected ?Tenant $tenant = null;
    protected ?User $user = null;
    protected ?string $token = null;

    /**
     * Configuración base para todos los tests
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setTenantContext(null);

        // Desactivar eventos por defecto
        Event::fake();

        // Desactivar envío de emails por defecto
        Mail::fake();

        // Desactivar colas por defecto
        Queue::fake();
    }

    /**
     * Helper para crear un tenant de prueba
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        $this->tenant = Tenant::factory()->create($attributes);

        return $this->tenant;
    }

    /**
     * Helper para crear un usuario autenticado con tenant
     */
    protected function createAuthenticatedUser(array $attributes = []): array
    {
        if (! $this->tenant) {
            $this->createTenant();
        }

        $this->setTenantContext($this->tenant->id);

        $this->user = User::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
        ], $attributes));

        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->setTenantContext($this->tenant->id);

        return [
            'user' => $this->user,
            'tenant' => $this->tenant,
            'token' => $this->token,
            'headers' => $this->authHeaders(),
        ];
    }

    /**
     * Helper para obtener headers de autenticación
     */
    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Helper para verificar la estructura de respuesta API
     */
    protected function assertApiResponse($response, $status = 200)
    {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    /**
     * Helper para verificar la estructura de respuesta paginada
     */
    protected function assertPaginatedResponse($response, $status = 200)
    {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'links',
            ]);
    }

    /**
     * Helper para verificar la estructura de respuesta de error API
     */
    protected function assertApiError($response, $status = 400)
    {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    protected function setTenantContext(?int $tenantId): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantId === null) {
            $tenantContext->clear();

            return;
        }

        $tenantContext->set($tenantId);
    }

    protected function runInTenantContext(int $tenantId, callable $callback): mixed
    {
        return app(TenantContext::class)->run($tenantId, $callback);
    }
}
