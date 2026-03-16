<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\Feature;
use Tests\TestCase;

class CabinApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_list_cabins(): void
    {
        Cabin::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/cabins');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_cabin(): void
    {
        $data = [
            'name' => 'Cabaña del Lago',
            'description' => 'Hermosa cabaña con vista al lago',
            'capacity' => 4,
            'is_active' => true,
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/cabins', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Cabaña del Lago')
            ->assertJsonPath('data.capacity', 4);

        $this->assertDatabaseHas('cabins', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabaña del Lago',
        ]);
    }

    public function test_can_create_cabin_with_features(): void
    {
        $features = Feature::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $data = [
            'name' => 'Cabaña Premium',
            'capacity' => 6,
            'feature_ids' => $features->pluck('id')->toArray(),
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/cabins', $data);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.features');
    }

    public function test_cannot_create_cabin_with_features_from_other_tenant(): void
    {
        $otherTenant = $this->createTenant(['name' => 'Otro tenant']);
        $foreignFeature = Feature::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/cabins', [
                'name' => 'Cabaña inválida',
                'capacity' => 4,
                'feature_ids' => [$foreignFeature->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['feature_ids.0']);
    }

    public function test_cannot_create_cabin_without_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/cabins', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'capacity']);
    }

    public function test_can_show_cabin_with_features(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $features = Feature::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        $cabin->features()->attach($features);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/cabins/{$cabin->id}");

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.id', $cabin->id);
        $response->assertJsonCount(2, 'data.features');
    }

    public function test_can_update_cabin(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/cabins/{$cabin->id}", [
                'name' => 'Cabaña Renovada',
                'capacity' => 8,
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonPath('data.name', 'Cabaña Renovada');
        $response->assertJsonPath('data.capacity', 8);
    }

    public function test_can_update_cabin_features(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $oldFeatures = Feature::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        $cabin->features()->attach($oldFeatures);

        $newFeatures = Feature::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/cabins/{$cabin->id}", [
                'feature_ids' => $newFeatures->pluck('id')->toArray(),
            ]);

        $this->assertApiResponse($response);
        $response->assertJsonCount(3, 'data.features');
    }

    public function test_cannot_update_cabin_with_features_from_other_tenant(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherTenant = $this->createTenant(['name' => 'Tenant ajeno']);
        $foreignFeature = Feature::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/cabins/{$cabin->id}", [
                'feature_ids' => [$foreignFeature->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['feature_ids.0']);
    }

    public function test_can_delete_cabin(): void
    {
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/cabins/{$cabin->id}");

        $this->assertApiResponse($response);
        $this->assertSoftDeleted('cabins', ['id' => $cabin->id]);
    }

    public function test_can_filter_by_capacity(): void
    {
        Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 2]);
        Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 4]);
        Cabin::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 6]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/cabins?min_capacity=4');

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(2, 'data');
    }

    /**
     * RIESGO #9: Relaciones soft-deleted sin withTrashed()
     * Cuando una cabaña es soft-deleted, la relación BelongsTo en Reservation
     * devuelve NULL en lugar de incluir el registro eliminado.
     */
    public function test_soft_deleted_cabin_returns_null_in_reservation_relation(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #9 - Relaciones soft-deleted sin withTrashed() devuelven null');

        // Crear cabaña y cliente
        $cabin = Cabin::factory()->create(['tenant_id' => $this->tenant->id]);
        $client = \App\Models\Client::factory()->create(['tenant_id' => $this->tenant->id]);

        // Crear reserva que apunta a esa cabaña
        $reservation = \App\Models\Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $cabin->id,
            'client_id' => $client->id,
        ]);

        // Verificar que la relación funciona antes del borrado
        $this->assertNotNull($reservation->fresh()->cabin);
        $this->assertEquals($cabin->id, $reservation->fresh()->cabin->id);

        // Soft-delete la cabaña
        $cabin->delete();

        // Recargar la reserva y acceder a la relación
        $reservation->refresh();
        $reservationCabin = $reservation->cabin;

        // PROBLEMA: $reservationCabin es NULL porque la relación no usa withTrashed()
        $this->assertNull($reservationCabin, 'La relación cabin() devuelve NULL después del soft-delete');

        // En un caso real, esto causaría:
        // - Historiales de reservas con cabin: null
        // - Errores null pointer al acceder a $reservation->cabin->name
        // - Endpoints que cargan relaciones exponen registros con valor null inesperadamente
    }

    /**
     * RIESGO #10: AvailabilityService carga client sin withTrashed() en calendario
     * Cuando se calcula el calendario de disponibilidad y una reserva tiene
     * un cliente soft-deleted, acceder a $reservation->client->name falla con error.
     */
    public function test_availability_calendar_fails_with_soft_deleted_client(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #10 - AvailabilityService accede a client->name de cliente eliminado');

        // Crear cabaña activa
        $cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Crear cliente y reserva confirming
        $client = \App\Models\Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $reservation = \App\Models\Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $cabin->id,
            'client_id' => $client->id,
            'status' => \App\Models\Reservation::STATUS_CONFIRMED,
            'check_in_date' => now()->addDays(1),
            'check_out_date' => now()->addDays(3),
        ]);

        // Soft-delete el cliente
        $client->delete();

        // Antes de la corrección, AvailabilityService::getCalendarDays() fará:
        // call to a member function name() on null
        // porque $reservation->client devuelve NULL tras el soft-delete
        $availabilityService = app(\App\Services\AvailabilityService::class);

        $from = now();
        $to = now()->addDays(10);

        // Esta llamada debería fallar o devolver datos incompletos
        try {
            $calendar = $availabilityService->getCalendarDays($from, $to);
            // Si no falla, verificar que el client_name está incompleto o null
            $cabinReservations = collect($calendar['cabins'])->firstWhere('id', $cabin->id)['reservations'] ?? [];
            $this->assertEmpty($cabinReservations, 'Las reservas con client soft-deleted no deberían aparecer completas');
        } catch (\Error $e) {
            // Error esperado: Call to a member function name() on null
            $this->assertStringContainsString('null', $e->getMessage());
        }
    }

    /**
     * RIESGO #11: PriceCalculatorService retorna 0 silenciosamente sin validar cabin
     * El método getPriceByCabinAndGuests() devuelve 0 si no encuentra precio,
     * sin verificar que la cabaña exista. Esto puede pasar desapercibido.
     */
    public function test_price_calculator_returns_zero_for_nonexistent_cabin_silently(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #11 - PriceCalculatorService retorna 0 sin validar que cabin existe');

        $priceCalculatorService = app(\App\Services\PriceCalculatorService::class);

        // Usar un ID de cabaña que NO existe
        $nonexistentCabinId = 99999;

        // Crear rango de precios genérico
        $priceGroup = \App\Models\PriceGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        \App\Models\PriceRange::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price_group_id' => $priceGroup->id,
        ]);

        // Crear precio para este grupo (y la cabaña no existente)
        \App\Models\CabinPriceByGuests::factory()->create([
            'cabin_id' => $nonexistentCabinId,
            'price_group_id' => $priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 0,
        ]);

        // Calcular precio
        $checkIn = now()->addDays(1)->toCarbon();
        $checkOut = now()->addDays(3)->toCarbon();
        $numGuests = 2;

        $result = $priceCalculatorService->calculatePrice(
            $checkIn,
            $checkOut,
            $nonexistentCabinId,
            $numGuests,
        );

        // PROBLEMA: El precio es 0 sin advertencia
        // No hay excepción que indique que la cabaña no existe
        // El cliente recibe un "precio válido" de 0 sin saber que hubo un problema
        $this->assertEquals(0, $result['total'], 'Se devuelve precio total = 0 sin error para cabaña inexistente');

        // En un caso real:
        // - Cotizaciones con cabin_id inválido devuelven total: 0 sin error
        // - Reportes de precios son silenciosamente incorrectos
        // - Silent failures en históricos si una cabaña desaparece de la DB
    }
}
