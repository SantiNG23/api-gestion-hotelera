<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\FrontendObservabilityLog;
use Tests\TestCase;

class FrontendObservabilityApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_store_frontend_warn_and_error_logs_successfully(): void
    {
        $warnResponse = $this->postJson(
            '/api/v1/observability/frontend-logs',
            $this->validPayload('warn', [
                'meta' => [
                    'authorization' => 'Bearer super-secret',
                    'status' => 429,
                ],
            ]),
            $this->authHeaders()
        );

        $warnResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'ingested_at'],
            ]);

        $errorResponse = $this->postJson(
            '/api/v1/observability/frontend-logs',
            $this->validPayload('error', [
                'event_name' => 'auth.login.failed',
                'args' => ['Error al autenticar usuario'],
            ]),
            $this->authHeaders()
        );

        $errorResponse->assertStatus(201);

        $warnId = $warnResponse->json('data.id');
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F\-]{36}$/', (string) $warnId);

        $this->assertDatabaseCount('frontend_observability_logs', 2);

        $warnLog = FrontendObservabilityLog::query()->findOrFail($warnId);
        $this->assertSame('[REDACTED]', $warnLog->meta['authorization']);
    }

    public function test_returns_422_for_invalid_payload(): void
    {
        $response = $this->postJson(
            '/api/v1/observability/frontend-logs',
            [
                'timestamp' => 'invalid-date',
                'level' => 'info',
                'scope' => '',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Error de validación',
            ])
            ->assertJsonValidationErrors(['timestamp', 'level', 'scope', 'event_name']);
    }

    public function test_returns_422_for_non_parseable_iso8601_timestamp(): void
    {
        $response = $this->postJson(
            '/api/v1/observability/frontend-logs',
            $this->validPayload('warn', [
                'timestamp' => '2026-02-31T10:30:00Z',
            ]),
            $this->authHeaders()
        );

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Error de validación',
            ])
            ->assertJsonValidationErrors(['timestamp']);
    }

    public function test_returns_401_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/observability/frontend-logs', $this->validPayload('warn'));

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'No autenticado',
            ]);
    }

    public function test_returns_429_when_burst_rate_limit_is_exceeded(): void
    {
        $payload = $this->validPayload('warn');

        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson('/api/v1/observability/frontend-logs', $payload, $this->authHeaders());
            $response->assertStatus(201);
        }

        $response = $this->postJson('/api/v1/observability/frontend-logs', $payload, $this->authHeaders());

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Demasiadas solicitudes',
            ]);
    }

    public function test_truncates_request_id_header_to_100_chars(): void
    {
        $longRequestId = str_repeat('a', 180);
        $headers = array_merge($this->authHeaders(), [
            'X-Request-Id' => $longRequestId,
        ]);

        $response = $this->postJson(
            '/api/v1/observability/frontend-logs',
            $this->validPayload('warn'),
            $headers
        );

        $response->assertStatus(201);

        $log = FrontendObservabilityLog::query()->latest('ingested_at')->firstOrFail();

        $this->assertNotNull($log->request_id);
        $this->assertSame(100, strlen((string) $log->request_id));
        $this->assertSame(substr($longRequestId, 0, 100), $log->request_id);
    }

    private function validPayload(string $level, array $overrides = []): array
    {
        return array_replace_recursive([
            'timestamp' => now()->toISOString(),
            'level' => $level,
            'scope' => 'app',
            'context' => ['api', 'response-interceptor'],
            'event_name' => 'api.response.429',
            'meta' => [
                'status' => 429,
                'url' => '/reservations',
                'method' => 'get',
            ],
            'args' => ['Demasiadas solicitudes'],
        ], $overrides);
    }
}
