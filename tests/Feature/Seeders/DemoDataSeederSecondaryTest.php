<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Tenant;
use Database\Seeders\DemoDataSeeder;
use Tests\TestCase;

class DemoDataSeederSecondaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoDataSeeder::class);
    }

    public function test_demo_seed_supports_blocked_ranges_with_expired_pending_excluded(): void
    {
        $headers = $this->loginHeaders('smoke-sierra-clara', 'smoke.sierra@miradordeluz.test');
        $tenant = Tenant::query()->where('slug', 'smoke-sierra-clara')->firstOrFail();

        $coihueId = $this->runInTenantContext($tenant->id, fn (): ?int => Cabin::query()
            ->where('name', 'SMOKE A | Coihue Grupo')
            ->value('id'));

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/availability/'.$coihueId.'?from=2030-04-09&to=2030-04-30');

        $response->assertOk();

        $ranges = collect($response->json('data.blocked_ranges'));

        $this->assertSame(
            ['2030-04-10', '2030-04-15', '2030-04-22'],
            $ranges->pluck('from')->all()
        );
        $this->assertNotContains('2030-04-25', $ranges->pluck('from')->all());
    }

    public function test_demo_seed_supports_client_history_for_secondary_flows(): void
    {
        $headers = $this->loginHeaders('smoke-sierra-clara', 'smoke.sierra@miradordeluz.test');
        $tenant = Tenant::query()->where('slug', 'smoke-sierra-clara')->firstOrFail();

        $historyClientId = $this->runInTenantContext($tenant->id, fn (): ?int => Client::query()
            ->where('dni', '41000014')
            ->value('id'));

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/clients/'.$historyClientId);

        $response->assertOk()
            ->assertJsonPath('data.name', 'SMOKE Historial A');

        $reservations = collect($response->json('data.reservations'));

        $this->assertCount(3, $reservations);
        $this->assertEqualsCanonicalizing(
            ['cancelled', 'confirmed', 'finished'],
            $reservations->pluck('status')->all()
        );
        $this->assertContains('SMOKE A | Sauce Historial', $reservations->pluck('cabin.name')->all());
    }

    public function test_demo_seed_keeps_soft_deleted_relations_and_active_delete_targets(): void
    {
        $headers = $this->loginHeaders('smoke-sierra-clara', 'smoke.sierra@miradordeluz.test');
        $tenant = Tenant::query()->where('slug', 'smoke-sierra-clara')->firstOrFail();

        [$archivedReservationId, $softDeletedClientId, $softDeletedCabinId, $deleteTargetClientId, $deleteTargetCabinId] = $this->runInTenantContext($tenant->id, function (): array {
            return [
                Reservation::query()->where('notes', '[SMOKE:A:ARCHIVED_RELATIONS]')->value('id'),
                Client::withTrashed()->where('dni', '41000015')->value('id'),
                Cabin::withTrashed()->where('name', 'SMOKE A | Arrayan Archivada')->value('id'),
                Client::query()->where('dni', '41000016')->value('id'),
                Cabin::query()->where('name', 'SMOKE A | Sauce Historial')->value('id'),
            ];
        });

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/reservations/'.$archivedReservationId);

        $response->assertOk()
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.client.name', 'SMOKE Archivado A')
            ->assertJsonPath('data.cabin.name', 'SMOKE A | Arrayan Archivada');

        $this->assertSoftDeleted('clients', ['id' => $softDeletedClientId]);
        $this->assertSoftDeleted('cabins', ['id' => $softDeletedCabinId]);
        $this->assertDatabaseHas('clients', ['id' => $deleteTargetClientId, 'deleted_at' => null]);
        $this->assertDatabaseHas('cabins', ['id' => $deleteTargetCabinId, 'deleted_at' => null]);
    }

    private function loginHeaders(string $tenantSlug, string $email): array
    {
        $response = $this->postJson('/api/v1/auth/login', [
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
