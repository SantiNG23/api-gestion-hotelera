<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\PublicQuote\PublicQuoteTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePublicQuoteTenant
{
    public const TENANT_ATTRIBUTE = 'publicQuoteTenant';

    public function __construct(
        private readonly PublicQuoteTokenService $tokenService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::query()
            ->where('slug', $request->route('tenant_slug'))
            ->where('is_active', true)
            ->first();

        if ($tenant === null) {
            return response()->json([
                'success' => false,
                'message' => 'Recurso no encontrado',
                'errors' => [
                    'code' => ['tenant_not_found'],
                ],
            ], 404);
        }

        $plainTextToken = trim((string) $request->header(PublicQuoteTokenService::HEADER_NAME, ''));

        if ($plainTextToken === '' ||
            $tenant->public_quote_token_hash === null ||
            ! $this->tokenService->matches($plainTextToken, $tenant->public_quote_token_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
                'errors' => [
                    'code' => ['invalid_public_quote_token'],
                ],
            ], 401);
        }

        $request->attributes->set(self::TENANT_ATTRIBUTE, $tenant);

        return $next($request);
    }
}
