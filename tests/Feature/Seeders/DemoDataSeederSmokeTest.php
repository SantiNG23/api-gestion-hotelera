<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Cabin;
use App\Models\Feature;
use App\Models\Tenant;
use Database\Seeders\DemoDataSeeder;
use Tests\TestCase;

class DemoDataSeederSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoDataSeeder::class);
    }

    public function test_demo_seed_supports_auth_bootstrap_and_lookup_isolation(): void
    {
        $tenantAHeaders = $this->loginHeaders('smoke-sierra-clara', 'smoke.sierra@miradordeluz.test');

        $this->withHeaders($tenantAHeaders)
            ->getJson('/api/v1/auth')
            ->assertOk()
            ->assertJsonPath('data.email', 'smoke.sierra@miradordeluz.test');

        $this->withHeaders($tenantAHeaders)
            ->getJson('/api/v1/clients/dni/41000001')
            ->assertOk()
            ->assertJsonPath('data.name', 'SMOKE Cliente Lookup A');
    }

    public function test_demo_seed_exposes_equivalent_lookup_dataset_for_second_tenant(): void
    {
        $tenantBHeaders = $this->loginHeaders('smoke-bosque-sereno', 'smoke.bosque@miradordeluz.test');

        $this->withHeaders($tenantBHeaders)
            ->getJson('/api/v1/auth')
            ->assertOk()
            ->assertJsonPath('data.email', 'smoke.bosque@miradordeluz.test');

        $this->withHeaders($tenantBHeaders)
            ->getJson('/api/v1/clients/dni/41000001')
            ->assertOk()
            ->assertJsonPath('data.name', 'SMOKE Cliente Lookup B');
    }

    public function test_demo_seed_supports_cabin_creation_and_pricing_smoke(): void
    {
        $headers = $this->loginHeaders('smoke-sierra-clara', 'smoke.sierra@miradordeluz.test');
        $tenant = Tenant::query()->where('slug', 'smoke-sierra-clara')->firstOrFail();

        [$wifiId, $parrillaId, $coihueId] = $this->runInTenantContext($tenant->id, function (): array {
            return [
                Feature::query()->where('name', 'SMOKE A | Wifi')->value('id'),
                Feature::query()->where('name', 'SMOKE A | Parrilla')->value('id'),
                Cabin::query()->where('name', 'SMOKE A | Coihue Grupo')->value('id'),
            ];
        });

        $createCabinResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/cabins', [
                'name' => 'SMOKE A | Alta Manual',
                'description' => 'Cabana creada desde smoke deterministico.',
                'capacity' => 5,
                'feature_ids' => [$wifiId, $parrillaId],
            ]);

        $createCabinResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'SMOKE A | Alta Manual');

        $ratesResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/price-ranges/applicable-rates?start_date=2030-04-09&end_date=2030-04-10');

        $ratesResponse->assertOk();
        $this->assertSame('SMOKE A | Feriado Largo', $ratesResponse->json('data.rates.2030-04-09.group_name'));

        $quoteResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/reservations/quote', [
                'cabin_id' => $coihueId,
                'check_in_date' => '2030-05-10',
                'check_out_date' => '2030-05-12',
                'num_guests' => 3,
            ]);

        $quoteResponse->assertOk()
            ->assertJsonPath('data.total', 594.0)
            ->assertJsonPath('data.is_available', true);
    }

    public function test_demo_seed_supports_reservations_availability_calendar_and_daily_summary(): void
    {
        $headers = $this->loginHeaders('smoke-sierra-clara', 'smoke.sierra@miradordeluz.test');
        $tenant = Tenant::query()->where('slug', 'smoke-sierra-clara')->firstOrFail();

        [$alerceId, $coihueId] = $this->runInTenantContext($tenant->id, function (): array {
            return [
                Cabin::query()->where('name', 'SMOKE A | Alerce Familiar')->value('id'),
                Cabin::query()->where('name', 'SMOKE A | Coihue Grupo')->value('id'),
            ];
        });

        $reservationResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/reservations', [
                'cabin_id' => $alerceId,
                'check_in_date' => '2030-05-20',
                'check_out_date' => '2030-05-22',
                'num_guests' => 4,
                'client' => [
                    'name' => 'SMOKE Cliente Reserva API',
                    'dni' => '41000021',
                    'email' => 'reserva.api@miradordeluz.test',
                ],
            ]);

        $reservationResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'pending_confirmation')
            ->assertJsonPath('data.total_price', 864.0);

        $this->withHeaders($headers)
            ->getJson('/api/v1/availability?cabin_id='.$coihueId.'&check_in_date=2030-04-15&check_out_date=2030-04-17')
            ->assertOk()
            ->assertJsonPath('data.is_available', false);

        $calendarResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/availability/calendar?from=2030-04-09&to=2030-04-18');

        $calendarResponse->assertOk();
        $calendarCabins = collect($calendarResponse->json('data.cabins'));
        $alerceReservations = $calendarCabins->firstWhere('name', 'SMOKE A | Alerce Familiar')['reservations'] ?? [];
        $coihueReservations = $calendarCabins->firstWhere('name', 'SMOKE A | Coihue Grupo')['reservations'] ?? [];

        $this->assertSame(['SMOKE Reserva Base A'], array_column($alerceReservations, 'client_name'));
        $this->assertContains('BLOQUEO DE FECHAS', array_column($coihueReservations, 'client_name'));

        $summaryResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/daily-summary?date=2030-04-10');

        $summaryResponse->assertOk()
            ->assertJsonPath('data.has_events', true)
            ->assertJsonPath('data.check_ins.0.cabin_name', 'SMOKE A | Alerce Familiar')
            ->assertJsonPath('data.check_outs.0.cabin_name', 'SMOKE A | Cipres Pareja');

        $this->assertContains(
            'SMOKE A | Coihue Grupo',
            array_column($summaryResponse->json('data.expiring_pending'), 'cabin_name')
        );
    }

    private function loginHeaders(string $tenantSlug, string $email): array
    {
        $response = $this->postJson('/api/v1/auth', [
            'tenant_slug' => $tenantSlug,
            'email' => $email,
            'password' => 'Demo123!',
        ]);

        $response->assertOk();

        return [
            'Authorization' => 'Bearer '.$response->json('data.token'),
            'Accept' => 'application/json',
        ];
    }
}
