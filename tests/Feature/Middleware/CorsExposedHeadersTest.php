<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class CorsExposedHeadersTest extends TestCase
{
    public function test_cors_exposes_rate_limit_headers_for_api_requests(): void
    {
        $response = $this->call('GET', '/api/v1/auth', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => 'https://frontend.example.com',
        ]);

        $response->assertHeader('Access-Control-Expose-Headers');

        $this->assertSame(
            'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, X-RateLimit-Burst-Limit, X-RateLimit-Burst-Remaining, X-RateLimit-Burst-Reset',
            $response->headers->get('Access-Control-Expose-Headers')
        );
    }
}
