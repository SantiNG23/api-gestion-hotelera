<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class ValidateApiHeadersTest extends TestCase
{
    public function test_accept_header_allows_application_json_with_charset(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json; charset=utf-8',
                'CONTENT_TYPE' => 'application/json; charset=utf-8',
            ],
            json_encode([
                'email' => 'middleware@example.com',
                'password' => 'Password123!',
                'tenant_slug' => 'test-tenant',
            ])
        );

        $this->assertNotEquals(400, $response->getStatusCode());
    }

    public function test_accept_header_allows_json_among_multiple_media_types(): void
    {
        $response = $this->call(
            'GET',
            '/api/v1/auth',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json, text/plain, */*',
            ]
        );

        $this->assertNotEquals(400, $response->getStatusCode());
    }

    public function test_content_type_rejects_non_json_media_type(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'text/plain',
            ],
            json_encode([
                'email' => 'middleware2@example.com',
                'password' => 'Password123!',
                'tenant_slug' => 'test-tenant',
            ])
        );

        $response->assertStatus(400)
            ->assertJsonPath('message', 'El header Content-Type debe ser application/json');
    }

    public function test_accept_header_rejects_non_json_media_type(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'text/html',
                'CONTENT_TYPE' => 'application/json',
            ]
            , json_encode([
                'email' => 'html@example.com',
                'password' => 'Password123!',
                'tenant_slug' => 'test-tenant',
            ])
        );

        $response->assertStatus(400)
            ->assertJsonPath('message', 'El header Accept debe ser application/json');
    }
}