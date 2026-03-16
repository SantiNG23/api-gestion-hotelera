<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use App\Services\ReservationService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelExpiredPendingReservations extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'reservations:cancel-expired {--tenant=} {--all-tenants : Ejecuta el proceso para todos los tenants activos con trazabilidad operativa}';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Cancela automáticamente las reservas pendientes que han excedido su límite de tiempo (pending_until)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(
        ReservationService $reservationService,
        TenantContextResolver $tenantContextResolver,
        TenantContext $tenantContext,
    ): int {
        if ($this->option('tenant') !== null && $this->option('all-tenants')) {
            $this->error('Usa --tenant o --all-tenants, no ambos al mismo tiempo.');

            return Command::FAILURE;
        }

        if ($this->option('all-tenants')) {
            $this->warn('Modo administrativo --all-tenants habilitado.');
            Log::warning('reservations:cancel-expired ejecutado en modo administrativo --all-tenants');

            $result = ['cancelled' => 0, 'failed' => 0];

            foreach (Tenant::query()->where('is_active', true)->pluck('id') as $tenantId) {
                $tenantResult = $this->runForTenant(
                    $tenantContext,
                    $reservationService,
                    (int) $tenantId,
                );

                $result['cancelled'] += $tenantResult['cancelled'];
                $result['failed'] += $tenantResult['failed'];
            }

            return $this->renderResult($result);
        }

        $tenantId = $this->parseTenantOption();

        if ($tenantId === null) {
            $this->error(MissingTenantContextException::forCommand()->getMessage());

            return Command::FAILURE;
        }

        $resolvedTenantId = $tenantContextResolver->resolveForCommand($tenantId);
        $result = $this->runForTenant($tenantContext, $reservationService, $resolvedTenantId);

        return $this->renderResult($result);
    }

    /**
     * @return array{cancelled: int, failed: int}
     */
    private function runForTenant(TenantContext $tenantContext, ReservationService $reservationService, int $tenantId): array
    {
        $this->info("Iniciando cancelacion de reservas expiradas para tenant {$tenantId}...");

        return $tenantContext->run(
            $tenantId,
            fn (): array => $reservationService->autoCalcellExpiredPending(),
        );
    }

    /**
     * @param  array{cancelled: int, failed: int}  $result
     */
    private function renderResult(array $result): int
    {
        $this->info('Operacion completada:');
        $this->info("Reservas canceladas: {$result['cancelled']}");

        if ($result['failed'] > 0) {
            $this->warn("Reservas fallidas: {$result['failed']}");
        }

        return $result['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function parseTenantOption(): ?int
    {
        $tenantOption = $this->option('tenant');

        if ($tenantOption === null) {
            return null;
        }

        if (! is_numeric((string) $tenantOption)) {
            return null;
        }

        return (int) $tenantOption;
    }
}
