<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FrontendObservabilityLog;
use Illuminate\Console\Command;

class PurgeFrontendObservabilityLogs extends Command
{
    protected $signature = 'observability:purge-frontend-logs {--days=30 : Días de retención}';

    protected $description = 'Purga logs de observabilidad frontend fuera de la ventana de retención';

    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $cutoff = now()->subDays($days);

        $deleted = FrontendObservabilityLog::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Purgados {$deleted} logs de observabilidad frontend (retención: {$days} días).");

        return self::SUCCESS;
    }
}
