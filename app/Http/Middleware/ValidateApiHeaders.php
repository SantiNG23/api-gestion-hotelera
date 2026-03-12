<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->validateAcceptHeader($request)) {
            return response()->json([
                'success' => false,
                'message' => 'El header Accept debe ser application/json',
            ], 400);
        }

        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            if (! $this->validateContentTypeHeader($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El header Content-Type debe ser application/json',
                ], 400);
            }
        }

        return $next($request);
    }

    /**
     * Validate the Accept header.
     */
    protected function validateAcceptHeader(Request $request): bool
    {
        return $request->hasHeader('Accept') &&
            $this->containsJsonMediaType($request->header('Accept'));
    }

    /**
     * Validate the Content-Type header.
     */
    protected function validateContentTypeHeader(Request $request): bool
    {
        return $request->hasHeader('Content-Type') &&
               $this->containsJsonMediaType($request->header('Content-Type'));
    }

    /**
     * Determina si el header contiene un media type JSON válido.
     */
    private function containsJsonMediaType(string $headerValue): bool
    {
        foreach (explode(',', strtolower($headerValue)) as $part) {
            $mediaType = trim(explode(';', trim($part))[0]);

            if ($mediaType === 'application/json' || str_ends_with($mediaType, '+json')) {
                return true;
            }
        }

        return false;
    }
}
