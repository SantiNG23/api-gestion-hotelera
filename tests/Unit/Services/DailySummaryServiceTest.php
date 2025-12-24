<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Services\DailySummaryService;
use Carbon\Carbon;
use Tests\TestCase;

class DailySummaryServiceTest extends TestCase
{
    private DailySummaryService $service;
    protected ?Tenant $localTenant = null;
    private ?Cabin $localCabin = null;
    private ?Client $localClient = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DailySummaryService();
        $this->localTenant = Tenant::factory()->create();
        $this->localCabin = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        $this->localClient = Client::factory()->create(['tenant_id' => $this->localTenant->id]);
    }

    public function test_returns_check_ins_for_date(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(3),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertEquals(1, $summary['check_ins']->count());
    }

    public function test_does_not_return_pending_as_check_ins(): void
    {
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(3),
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertEquals(0, $summary['check_ins']->count());
    }

    public function test_returns_check_outs_for_date(): void
    {
        Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today()->subDays(3),
            'check_out_date' => Carbon::today(),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertEquals(1, $summary['check_outs']->count());
    }

    public function test_returns_expiring_pending_reservations_by_pending_until(): void
    {
        // Caso 1: Reserva pendiente que vence hoy (falta seña/depósito)
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::today()->setHour(18),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertEquals(1, $summary['expiring_pending']->count());
    }

    public function test_returns_confirmed_reservations_without_balance_payment(): void
    {
        // Caso 2: Reserva confirmada de hoy sin pago de balance
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(3),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        // Debe estar en check_ins y en expiring_pending
        $this->assertEquals(1, $summary['check_ins']->count());
        $this->assertEquals(1, $summary['expiring_pending']->count());
    }

    public function test_daily_summary_has_events_true_when_events_exist(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(3),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertTrue($summary['has_events']);
        $this->assertEquals(1, $summary['check_ins']->count());
    }

    public function test_daily_summary_has_events_false_when_no_events(): void
    {
        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertFalse($summary['has_events']);
        $this->assertEquals(0, $summary['check_ins']->count());
        $this->assertEquals(0, $summary['check_outs']->count());
        $this->assertEquals(0, $summary['expiring_pending']->count());
    }
}
