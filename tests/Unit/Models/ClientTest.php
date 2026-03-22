<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientTest extends TestCase
{
    public function test_system_client_cannot_be_updated(): void
    {
        Event::fakeExcept([
            'eloquent.updating: '.Client::class,
        ]);

        $tenant = Tenant::factory()->create();
        $this->setTenantContext($tenant->id);
        $client = Client::factory()->create([
            'tenant_id' => $tenant->id,
            'dni' => Client::DNI_BLOCK,
            'name' => 'BLOQUEO DE FECHAS',
        ]);

        $this->expectException(ValidationException::class);

        $client->update(['name' => 'Intento de cambio']);
    }

    public function test_system_client_cannot_be_deleted(): void
    {
        Event::fakeExcept([
            'eloquent.deleting: '.Client::class,
        ]);

        $tenant = Tenant::factory()->create();
        $this->setTenantContext($tenant->id);
        $client = Client::factory()->create([
            'tenant_id' => $tenant->id,
            'dni' => Client::DNI_BLOCK,
            'name' => 'BLOQUEO DE FECHAS',
        ]);

        $this->expectException(ValidationException::class);

        $client->delete();
    }
}
