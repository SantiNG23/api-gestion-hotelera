<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use Illuminate\Database\Seeder;

/**
 * Seeder para precios de cabañas por cantidad de huéspedes
 *
 * Crea ejemplos de precios para diferentes cabañas, temporadas y cantidad de huéspedes
 */
class CabinPriceByGuestsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener cabañas activas y grupos de precio existentes
        $cabins = Cabin::where('is_active', true)->get();
        $priceGroups = PriceGroup::all();

        if ($cabins->isEmpty() || $priceGroups->isEmpty()) {
            $this->command->warn('No hay cabañas activas o grupos de precio. Ejecute otros seeders primero.');
            return;
        }

        $pricesCreated = 0;

        // Configurar qué grupos tendrán cabañas asignadas
        // Solo "Temporada Baja" y "Temporada Alta" tendrán cabañas
        // "Descuentos" y "Feriados" quedan sin cabañas como ejemplo
        $groupsWithCabins = ['Temporada Baja', 'Temporada Alta'];

        // Para cada grupo de precio que debe tener cabañas asignadas
        foreach ($priceGroups as $priceGroup) {
            // Verificar si este grupo debe tener cabañas
            if (!in_array($priceGroup->name, $groupsWithCabins)) {
                $this->command->line("  → '{$priceGroup->name}' sin cabañas asignadas (ejemplo de grupo sin cabañas)");
                continue;
            }

            $basePrice = (float) $priceGroup->price_per_night;

            // Crear precios para cada cabaña en este grupo
            foreach ($cabins as $cabin) {
                // Rango de huéspedes: desde 2 hasta la capacidad de la cabaña
                for ($numGuests = 2; $numGuests <= $cabin->capacity; $numGuests++) {
                    // Precio progresivo: basePrice por persona
                    // 2 personas = basePrice × 2
                    // 3 personas = basePrice × 3
                    // etc.
                    $price = $basePrice * $numGuests;

                    // Verificar que no exista ya (para permitir actualizar)
                    $priceRecord = CabinPriceByGuests::query()
                        ->where('cabin_id', $cabin->id)
                        ->where('price_group_id', $priceGroup->id)
                        ->where('num_guests', $numGuests)
                        ->first();

                    if (!$priceRecord) {
                        CabinPriceByGuests::create([
                            'tenant_id' => $cabin->tenant_id,
                            'cabin_id' => $cabin->id,
                            'price_group_id' => $priceGroup->id,
                            'num_guests' => $numGuests,
                            'price_per_night' => (int) $price,
                        ]);
                        $pricesCreated++;
                    }
                }
            }

            $this->command->line("  → '{$priceGroup->name}' asignado a {$cabins->count()} cabañas");
        }

        $this->command->info("✓ Precios de cabañas creados: {$pricesCreated} registros");
        $this->command->line("  Fórmula: Precio Total = Tarifa Base × Cantidad de Personas");
        $this->command->line("  Ejemplo: Temporada Baja (100/persona) → 2 pax = 200, 3 pax = 300");
    }
}
