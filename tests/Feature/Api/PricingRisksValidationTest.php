<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use App\Models\Tenant;
use App\Services\PriceGroupService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * Tests para validar riesgos identificados en el módulo de tarifas/precios
 *
 * Riesgos a validar:
 * - Riesgo 9: Soft delete bloquea índice único en CabinPriceByGuests
 * - Riesgo 10: getCompletePriceGroup oculta datos de cabañas soft-deleted
 * - Riesgo 11: Validación de cabinas depende del scope global de tenant
 * - Riesgo 12: Missing max:255 en ReservationQuoteRequest causa DoS potencial
 */
class PricingRisksValidationTest extends TestCase
{
    private Cabin $cabin;
    private PriceGroup $priceGroup;
    private Tenant $otherTenant;
    private Cabin $otherTenantCabin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();

        // Crear cabaña para este tenant
        $this->cabin = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 4,
        ]);

        // Crear grupo de precio
        $this->priceGroup = PriceGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tarifa Test',
            'price_per_night' => 100,
            'is_default' => true,
        ]);

        // Crear tenant ajeno para validar aislamiento
        $this->otherTenant = Tenant::factory()->create();
        $this->otherTenantCabin = Cabin::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'capacity' => 6,
        ]);
    }

    /**
     * Riesgo 9: Soft delete bloquea índice único en CabinPriceByGuests
     *
     * Escenario:
     * 1. Crear un precio para cabin+price_group+num_guests
     * 2. Eliminar ese precio (soft delete)
     * 3. Intentar crear un nuevo precio con la misma combinación
     * 4. Resultado esperado: Error de constraint violation porque el índice único NO excluye soft-deleted
     *
     * Impacto: Operativamente, una vez eliminado un precio, no se puede reutilizar esa combinación
     * sin intervención directa en BD.
     */
    public function test_soft_deleted_cabin_price_blocks_price_reuse(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #9 - Soft delete bloquea índice único en CabinPriceByGuests');

        // Step 1: Crear precio inicial
        $price = CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        $this->assertDatabaseHas('cabin_price_by_guests', [
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'deleted_at' => null,
        ]);

        // Step 2: Eliminar precio (soft delete)
        $price->delete();

        $this->assertSoftDeleted('cabin_price_by_guests', [
            'id' => $price->id,
        ]);

        // Step 3: Intentar crear el mismo precio nuevamente
        // Esperado: QueryException (Integrity constraint violation) porque el índice único
        // no excluye registros soft-deleted
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed');

        CabinPriceByGuests::create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 150, // diferente precio
        ]);
    }

    /**
     * Riesgo 10: getCompletePriceGroup oculta precios de cabanas soft-deleted
     *
     * Escenario:
     * 1. Crear un grupo completo con múltiples cabanas y precios
     * 2. Soft-delete una de las cabanas
     * 3. Consultar GET /price-groups/{id}/complete
     * 4. Resultado: Los precios asociados a la cabana eliminada desaparecen silenciosamente
     *    sin indicación de que existían datos asociados a esa cabana
     *
     * Impacto: Operativamente, los datos quedan huérfanos en BD pero invisibles en la API,
     * lo que puede llevar a duplicación lógica si se recrean precios pensando que no existen.
     */
    public function test_soft_deleted_cabin_hides_prices_in_complete_price_group(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #10 - getCompletePriceGroup oculta datos de cabañas soft-deleted');

        // Step 1: Crear dos cabanas
        $cabin1 = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabaña A',
            'capacity' => 4,
        ]);

        $cabin2 = Cabin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabaña B',
            'capacity' => 4,
        ]);

        // Step 2: Crear precios para ambas cabanas
        $price1 = CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $cabin1->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        $price2 = CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $cabin2->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 120,
        ]);

        // Step 3: Consultar el grupo completo ANTES de eliminar
        $service = new PriceGroupService;
        $completeBefore = $service->getCompletePriceGroup($this->priceGroup->id, $this->tenant->id);

        $this->assertCount(2, $completeBefore['cabins']);
        $this->assertEquals($cabin1->id, $completeBefore['cabins'][0]['id']);
        $this->assertEquals($cabin2->id, $completeBefore['cabins'][1]['id']);

        // Step 4: Soft-delete cabin2
        $cabin2->delete();

        $this->assertSoftDeleted('cabins', [
            'id' => $cabin2->id,
        ]);

        // Verificar que el precio aún existe en BD (soft-deleted cabin, pero price no)
        $this->assertDatabaseHas('cabin_price_by_guests', [
            'cabin_id' => $cabin2->id,
            'price_group_id' => $this->priceGroup->id,
            'deleted_at' => null, // el precio NO está soft-deleted
        ]);

        // Step 5: Consultar el grupo completo DESPUÉS de eliminar
        $completeAfter = $service->getCompletePriceGroup($this->priceGroup->id, $this->tenant->id);

        // Resultado: solo aparece cabin1, cabin2 y sus precios desaparecieron silenciosamente
        $this->assertCount(1, $completeAfter['cabins']);
        $this->assertEquals($cabin1->id, $completeAfter['cabins'][0]['id']);

        // El precio2 sigue existiendo en BD pero fue filtrado sin advertencia
        $priceStillExists = CabinPriceByGuests::withoutGlobalScopes()
            ->find($price2->id);
        $this->assertNotNull($priceStillExists);

        // Problema: no hay indicación de que los datos fueron ocultados porque la cabana fue eliminada
    }

    /**
     * Riesgo 11: Validación de cabinas depende del scope global de tenant
     *
     * Escenario:
     * 1. Crear un precio intentando usar una cabana de otro tenant
     * 2. En contexto normal (autenticado), validateCabinsAndPrices debería rechazarlo
     * 3. En contexto sin autenticación (CLI, job), el scope global podría fallar
     * 4. Resultado: posible contaminación de datos entre tenants
     *
     * Impacto: En contextos asincronos o CLI sin Auth explícita, se podría crear
     * CabinPriceByGuests asociando una cabana de otro tenant sin que se detecte.
     */
    public function test_cabin_validation_depends_on_global_scope_without_explicit_context(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #11 - Validación de cabinas depende del scope global de tenant');

        // Step 1: Intentar crear un precio con cabana de otro tenant
        // En contexto autenticado normal, debería fallar
        $data = [
            'cabins' => [
                [
                    'cabin_id' => $this->otherTenantCabin->id,
                    'prices' => [
                        [
                            'num_guests' => 2,
                            'price_per_night' => 100,
                        ],
                    ],
                ],
            ],
            'price_ranges' => [],
        ];

        // Step 2: Llamar al endpoint que valida y crea un grupo completo
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/price-groups/complete', [
                'name' => 'Test Contaminación',
                'priority' => 0,
                'is_default' => false,
                ...$data,
            ]);

        // Resultado esperado: debería rechazar porque la cabana pertenece a otro tenant
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cabins.0.cabin_id']);

        // Problema: Si el scope global falla en contexto sin Auth, podría permitirse
        // This test validates that the validation works in authenticated context,
        // but doesn't test the CLI/background job failure scenario directly
    }

    /**
     * Riesgo 11 alternativo: Validación sin Auth explícita
     *
     * Simula un contexto donde el scope global podría no estar activo
     */
    public function test_cabin_validation_without_auth_context(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #11 - Contexto sin Auth puede permitir contaminación');

        // Step 1: Obtener cabana de otro tenant
        $cabanaAjena = $this->otherTenantCabin;

        // Step 2: Intenta crear un precio sin contexto de autenticación
        // (simulando un job o comando artisan sin Auth)
        // En este contexto, BelongsToTenant::apply() podría no funcionar correctamente
        // porque no hay usuario autenticado para obtener el tenant_id

        // Step 3: Validar si el sistema permite crear un precio para una cabana de otro tenant
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $cabanaAjena->id, // cabaña de otro tenant
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        // Si esto llega aquí sin excepción, quiere decir que se creó un registro
        // que asocia una cabaña de otro tenant al grupo de este tenant = contaminación
    }

    /**
     * Riesgo 12: Missing max:255 en ReservationQuoteRequest - DoS potencial
     *
     * Escenario:
     * 1. POST /reservations/quote con num_guests = extremadamente grande (e.g., 9999999)
     * 2. CalculatePriceRequest valida con max:255, pero ReservationQuoteRequest no
     * 3. Un valor exorbitante podría generar:
     *    - Cálculos pesados / overflow
     *    - Comportamiento indeterminado
     *    - DoS potencial
     *
     * Impacto: Inconsistencia de validación permite inputs malformados que podría
     * generar DoS o comportamiento inesperado.
     */
    public function test_reservation_quote_missing_max_num_guests_validation(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #12 - Missing max:255 en ReservationQuoteRequest - DoS potencial');

        // Step 1: Crear infraestructura básica
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        // Step 2: Preparar payload con num_guests extremadamente grande
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        $payload = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 9999999, // extremadamente grande
        ];

        // Step 3: POST a /reservations/quote
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', $payload);

        // Resultado esperado (bug): La validación NO rechaza porque ReservationQuoteRequest
        // carece de max:255 en num_guests. La solicitud pasa y podría generar:
        // - Error 500 por overflow/comportamiento indeterminado
        // - Cálculo lento/pesado (DoS)
        // - Precio incorrecto

        // El test documenta que debería fallar en validación con status 422
        // pero actualmente falla en ejecución o pasa incorrectamente
        if ($response->status() === 422) {
            // Esperado: rechaza en validación
            $response->assertJsonValidationErrors(['num_guests']);
        } else {
            // Bug: acepta un valor inválido
            $this->assertTrue(
                in_array($response->status(), [500, 422]),
                'ReservationQuoteRequest debería validar max:255 en num_guests. Actualmente: '.$response->status()
            );
        }
    }

    /**
     * Riesgo 12 alternativo: Consistencia entre endpoints de cotización
     *
     * Valida que CalculatePriceRequest y ReservationQuoteRequest tengan el mismo contrato
     */
    public function test_calculate_price_vs_quote_request_validation_consistency(): void
    {
        $this->markTestIncomplete('TODO: Validar Riesgo #12 - Inconsistencia entre CalculatePriceRequest y ReservationQuoteRequest');

        // Step 1: Crear infraestructura básica
        CabinPriceByGuests::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cabin_id' => $this->cabin->id,
            'price_group_id' => $this->priceGroup->id,
            'num_guests' => 2,
            'price_per_night' => 100,
        ]);

        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->clone()->addDays(3);

        // Step 2: Payload con num_guests = 300 (> 255)
        $payloadExceedingMax = [
            'cabin_id' => $this->cabin->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'num_guests' => 300, // > max:255
        ];

        // Step 3: Probar CalculatePriceRequest (tiene max:255)
        $responseCalculate = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/calculate-price', $payloadExceedingMax);

        // Esperado: rechaza con 422
        $responseCalculate->assertStatus(422)
            ->assertJsonValidationErrors(['num_guests']);

        // Step 4: Probar ReservationQuoteRequest (NO tiene max:255)
        $responseQuote = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/reservations/quote', $payloadExceedingMax);

        // Bug: ReservationQuoteRequest lo acepta (status != 422)
        $this->assertNotEquals(
            $responseCalculate->status(),
            $responseQuote->status(),
            'CalculatePriceRequest y ReservationQuoteRequest tienen validaciones inconsistentes'
        );
    }
}
