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

        $checkIns = $this->service->getCheckInsForDate(Carbon::today());

        $this->assertEquals(1, $checkIns->count());
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

        $checkIns = $this->service->getCheckInsForDate(Carbon::today());

        $this->assertEquals(0, $checkIns->count());
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

        $checkOuts = $this->service->getCheckOutsForDate(Carbon::today());

        $this->assertEquals(1, $checkOuts->count());
    }

    public function test_returns_expiring_pending_reservations(): void
    {
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::today()->setHour(18),
        ]);

        $expiring = $this->service->getExpiringPendingReservations(Carbon::today());

        $this->assertEquals(1, $expiring->count());
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
        $this->assertEquals(1, $summary['summary']['check_ins_count']);
    }

    public function test_daily_summary_has_events_false_when_no_events(): void
    {
        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertFalse($summary['has_events']);
        $this->assertEquals(0, $summary['summary']['check_ins_count']);
        $this->assertEquals(0, $summary['summary']['check_outs_count']);
        $this->assertEquals(0, $summary['summary']['expiring_pending_count']);
    }

    public function test_occupancy_stats_calculation(): void
    {
        Cabin::factory()->count(2)->create(['tenant_id' => $this->localTenant->id]); // Total: 3

        // 1 cabaña ocupada hoy
        Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today()->subDay(),
            'check_out_date' => Carbon::today()->addDay(),
        ]);

        $stats = $this->service->getOccupancyStats(Carbon::today());

        $this->assertEquals(3, $stats['total_cabins']);
        $this->assertEquals(1, $stats['occupied_cabins']);
        $this->assertEquals(33.33, $stats['occupancy_rate']); // 1/3 * 100
    }
}

