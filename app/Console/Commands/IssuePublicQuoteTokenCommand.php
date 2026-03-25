<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PublicQuote\PublicQuoteTokenService;
use Illuminate\Console\Command;

class IssuePublicQuoteTokenCommand extends Command
{
    protected $signature = 'quote:issue-public-token
        {tenant-slug : Slug del tenant activo}';

    protected $description = 'Emite o regenera el token publico de cotizacion para un tenant activo';

    public function __construct(
        private readonly PublicQuoteTokenService $tokenService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantSlug = trim((string) $this->argument('tenant-slug'));

        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->first();

        if ($tenant === null) {
            $this->error("No se encontro un tenant activo con slug [{$tenantSlug}].");

            return self::FAILURE;
        }

        $hadExistingToken = $tenant->public_quote_token_hash !== null;
        $plainTextToken = $this->tokenService->generatePlainTextToken();

        $tenant->forceFill([
            'public_quote_token_hash' => $this->tokenService->hashToken($plainTextToken),
        ])->save();

        $this->line("Token publico de cotizacion (se muestra una sola vez): {$plainTextToken}");

        if ($hadExistingToken) {
            $this->info('Token regenerado. Se persistio solo el hash y el token anterior quedo invalidado.');

            return self::SUCCESS;
        }

        $this->info('Token emitido. Se persistio solo el hash y este valor no volvera a mostrarse.');

        return self::SUCCESS;
    }
}
