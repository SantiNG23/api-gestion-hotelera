<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicQuoteRateLimiter
{
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'public-quote:'.sha1($request->ip().'|'.$request->route('tenant_slug'));
        $maxAttempts = 10;
        $decaySeconds = 60;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return tap(response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes',
                'errors' => [
                    'code' => ['rate_limited'],
                ],
            ], 429), function (Response $response) use ($key, $maxAttempts, $retryAfter): void {
                $response->headers->add([
                    'X-RateLimit-Limit' => $maxAttempts,
                    'X-RateLimit-Remaining' => $this->limiter->remaining($key, $maxAttempts),
                    'X-RateLimit-Reset' => $retryAfter,
                ]);
            });
        }

        $this->limiter->hit($key, $decaySeconds);

        $response = $next($request);

        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $this->limiter->remaining($key, $maxAttempts),
            'X-RateLimit-Reset' => $this->limiter->availableIn($key),
        ]);

        return $response;
    }
}
