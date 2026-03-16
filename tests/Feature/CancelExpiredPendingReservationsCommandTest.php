<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cabin;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CancelExpiredPendingReservationsCommandTest extends TestCase
{
    public function test_command_fails_without_explicit_tenant_context(): void
    {
        $exitCode = Artisan::call('reservations:cancel-expired');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Falta contexto tenant activo', Artisan::output());
        $this->assertNull(app(TenantContext::class)->id());
    }

    public function test_command_cancels_expired_pending_reservations_for_explicit_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        [, $expiredReservation, $validReservation] = $this->runInTenantContext($tenant->id, function () use ($tenant): array {
            $cabin = Cabin::factory()->create([
                'tenant_id' => $tenant->id,
            ]);

            $expiredReservation = Reservation::factory()->create([
                'tenant_id' => $tenant->id,
                'cabin_id' => $cabin->id,
                'status' => Reservation::STATUS_PENDING_CONFIRMATION,
                'pending_until' => now()->subHour(),
            ]);

            $validReservation = Reservation::factory()->create([
                'tenant_id' => $tenant->id,
                'cabin_id' => $cabin->id,
                'status' => Reservation::STATUS_PENDING_CONFIRMATION,
                'pending_until' => now()->addHours(48),
            ]);

            return [$cabin, $expiredReservation, $validReservation];
        });

        $exitCode = Artisan::call('reservations:cancel-expired', [
            '--tenant' => $tenant->id,
        ]);

        $this->assertSame(0, $exitCode);
        $this->runInTenantContext($tenant->id, function () use ($expiredReservation, $validReservation): void {
            $expiredReservation->refresh();
            $this->assertTrue($expiredReservation->isCancelled());

            $validReservation->refresh();
            $this->assertTrue($validReservation->isPendingConfirmation());
        });
        $this->assertStringContainsString('Reservas canceladas: 1', Artisan::output());
        $this->assertNull(app(TenantContext::class)->id());
    }

    public function test_command_allows_explicit_admin_mode_for_all_tenants(): void
    {
        $firstTenant = Tenant::factory()->create();
        $secondTenant = Tenant::factory()->create();

        foreach ([$firstTenant, $secondTenant] as $tenant) {
            $this->runInTenantContext($tenant->id, function () use ($tenant): void {
                $cabin = Cabin::factory()->create([
                    'tenant_id' => $tenant->id,
                ]);

                Reservation::factory()->create([
                    'tenant_id' => $tenant->id,
                    'cabin_id' => $cabin->id,
                    'status' => Reservation::STATUS_PENDING_CONFIRMATION,
                    'pending_until' => now()->subHour(),
                ]);
            });
        }

        $exitCode = Artisan::call('reservations:cancel-expired', [
            '--all-tenants' => true,
        ]);

        $this->assertSame(0, $exitCode);
        foreach ([$firstTenant, $secondTenant] as $tenant) {
            $this->runInTenantContext($tenant->id, function (): void {
                $this->assertSame(0, Reservation::query()->where('status', Reservation::STATUS_PENDING_CONFIRMATION)->count());
                $this->assertSame(1, Reservation::query()->where('status', Reservation::STATUS_CANCELLED)->count());
            });
        }
        $this->assertStringContainsString('Modo administrativo --all-tenants habilitado.', Artisan::output());
        $this->assertNull(app(TenantContext::class)->id());
    }
}
