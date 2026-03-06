<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrontendObservabilityRateLimiter
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $identityKey = $request->user()
            ? (string) $request->user()->id
            : sha1((string) $request->ip());

        $minuteKey = 'frontend-observability:minute:'.$identityKey;
        $burstKey = 'frontend-observability:burst:'.$identityKey;

        if ($this->limiter->tooManyAttempts($burstKey, $this->getBurstMaxAttempts()) ||
            $this->limiter->tooManyAttempts($minuteKey, $this->getMinuteMaxAttempts())) {
            return $this->buildResponse($minuteKey, $burstKey);
        }

        $this->limiter->hit($burstKey, $this->getBurstDecaySeconds());
        $this->limiter->hit($minuteKey, $this->getMinuteDecaySeconds());

        $response = $next($request);

        return $this->addHeaders($response, $minuteKey, $burstKey);
    }

    protected function getMinuteMaxAttempts(): int
    {
        return 120;
    }

    protected function getMinuteDecaySeconds(): int
    {
        return 60;
    }

    protected function getBurstMaxAttempts(): int
    {
        return 20;
    }

    protected function getBurstDecaySeconds(): int
    {
        return 10;
    }

    protected function buildResponse(string $minuteKey, string $burstKey): Response
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Demasiadas solicitudes',
            'errors' => [
                'rate_limit' => ['Has excedido el límite de solicitudes permitidas para logs de observabilidad.'],
            ],
        ], 429);

        return $this->addHeaders($response, $minuteKey, $burstKey);
    }

    protected function addHeaders(Response $response, string $minuteKey, string $burstKey): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => (string) $this->getMinuteMaxAttempts(),
            'X-RateLimit-Remaining' => (string) $this->limiter->remaining($minuteKey, $this->getMinuteMaxAttempts()),
            'X-RateLimit-Reset' => (string) $this->limiter->availableIn($minuteKey),
            'X-RateLimit-Burst-Limit' => (string) $this->getBurstMaxAttempts(),
            'X-RateLimit-Burst-Remaining' => (string) $this->limiter->remaining($burstKey, $this->getBurstMaxAttempts()),
            'X-RateLimit-Burst-Reset' => (string) $this->limiter->availableIn($burstKey),
        ]);

        return $response;
    }
}
