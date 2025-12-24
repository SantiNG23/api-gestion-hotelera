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

    public function test_check_outs_only_includes_checked_in_status(): void
    {
        // Crear una reserva confirmada pero NO checked-in
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today()->subDays(3),
            'check_out_date' => Carbon::today(),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        // No debe incluirse en check_outs porque solo está confirmada
        $this->assertEquals(0, $summary['check_outs']->count());
    }

    public function test_multiple_events_in_same_day(): void
    {
        // Crear 2 check-ins
        $cabin1 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        $cabin2 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin1->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
        ]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin2->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
        ]);

        // Crear 1 check-out
        $cabin3 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin3->id,
            'check_in_date' => Carbon::today()->subDays(3),
            'check_out_date' => Carbon::today(),
        ]);

        // Crear 1 pending expiring (que también estará en expiring_pending)
        $cabin4 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id]);
        Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin4->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::today()->setHour(18),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertTrue($summary['has_events']);
        $this->assertEquals(2, $summary['check_ins']->count());
        $this->assertEquals(1, $summary['check_outs']->count());
        // 1 pending + 2 confirmed sin balance = 3 expiring_pending
        $this->assertEquals(3, $summary['expiring_pending']->count());
    }

    public function test_expiring_pending_includes_both_cases(): void
    {
        // Caso 1: Reserva pendiente con vencimiento hoy
        $pendingReservation = Reservation::factory()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => Carbon::today()->setHour(18),
        ]);

        // Caso 2: Reserva confirmada de hoy sin balance payment
        $confirmedReservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => Cabin::factory()->create(['tenant_id' => $this->localTenant->id])->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        // Ambas deben estar en expiring_pending
        $this->assertEquals(2, $summary['expiring_pending']->count());
        $expiredIds = $summary['expiring_pending']->pluck('id')->toArray();
        $this->assertContains($pendingReservation->id, $expiredIds);
        $this->assertContains($confirmedReservation->id, $expiredIds);
    }

    public function test_loads_relationships_for_check_ins(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(3),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        // Verificar que las relaciones están cargadas
        $checkIn = $summary['check_ins']->first();
        $this->assertNotNull($checkIn->client);
        $this->assertNotNull($checkIn->cabin);
    }

    public function test_orders_check_ins_by_cabin_id(): void
    {
        $cabin1 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id, 'name' => 'Cabaña A']);
        $cabin2 = Cabin::factory()->create(['tenant_id' => $this->localTenant->id, 'name' => 'Cabaña B']);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin2->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
        ]);

        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $cabin1->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        // Verificar que están ordenados por cabin_id
        $this->assertEquals($cabin1->id, $summary['check_ins']->first()->cabin_id);
        $this->assertEquals($cabin2->id, $summary['check_ins']->last()->cabin_id);
    }

    public function test_get_daily_summary_with_null_date_defaults_to_today(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
        ]);

        $summary = $this->service->getDailySummary(null);

        $this->assertTrue($summary['has_events']);
        $this->assertEquals(1, $summary['check_ins']->count());
    }

    public function test_does_not_include_future_check_outs(): void
    {
        // Crear check-out para mañana
        Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->localTenant->id,
            'client_id' => $this->localClient->id,
            'cabin_id' => $this->localCabin->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::tomorrow(),
        ]);

        $summary = $this->service->getDailySummary(Carbon::today());

        $this->assertEquals(0, $summary['check_outs']->count());
    }
}
