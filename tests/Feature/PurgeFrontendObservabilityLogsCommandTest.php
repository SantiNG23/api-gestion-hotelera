<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FrontendObservabilityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurgeFrontendObservabilityLogsCommandTest extends TestCase
{
    public function test_command_purges_only_logs_older_than_cutoff(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 6, 12, 0, 0));

        $cutoff = now()->subDays(30);

        $olderLog = $this->createFrontendLog([
            'occurred_at' => $cutoff->copy()->subSecond(),
        ]);

        $atCutoffLog = $this->createFrontendLog([
            'occurred_at' => $cutoff,
        ]);

        $recentLog = $this->createFrontendLog([
            'occurred_at' => now()->subDays(5),
        ]);

        $exitCode = Artisan::call('observability:purge-frontend-logs');

        $this->assertSame(0, $exitCode);

        $this->assertDatabaseMissing('frontend_observability_logs', [
            'id' => $olderLog->id,
        ]);

        $this->assertDatabaseHas('frontend_observability_logs', [
            'id' => $atCutoffLog->id,
        ]);

        $this->assertDatabaseHas('frontend_observability_logs', [
            'id' => $recentLog->id,
        ]);
    }

    public function test_command_respects_days_option(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 6, 12, 0, 0));

        $cutoff = now()->subDays(10);

        $olderThanTenDays = $this->createFrontendLog([
            'occurred_at' => $cutoff->copy()->subMinute(),
        ]);

        $withinTenDays = $this->createFrontendLog([
            'occurred_at' => $cutoff->copy()->addMinute(),
        ]);

        $exitCode = Artisan::call('observability:purge-frontend-logs', [
            '--days' => 10,
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertDatabaseMissing('frontend_observability_logs', [
            'id' => $olderThanTenDays->id,
        ]);

        $this->assertDatabaseHas('frontend_observability_logs', [
            'id' => $withinTenDays->id,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('retención: 10 días', $output);
    }

    private function createFrontendLog(array $attributes = []): FrontendObservabilityLog
    {
        return FrontendObservabilityLog::query()->create(array_merge([
            'tenant_id' => null,
            'user_id' => null,
            'level' => 'info',
            'scope' => 'test',
            'context' => ['source' => 'feature-test'],
            'event_name' => 'test.event',
            'meta' => null,
            'args' => null,
            'occurred_at' => now(),
            'ingested_at' => now(),
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'request_id' => 'req-test',
        ], $attributes));
    }
}
