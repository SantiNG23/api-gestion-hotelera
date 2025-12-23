<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Feature;
use App\Models\PriceGroup;
use App\Models\PriceRange;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed the application's database with demo data for frontend testing
     */
    public function run(): void
    {
        // Obtener o crear tenant para pruebas
        $tenant = Tenant::first() ?? Tenant::factory()->create([
            'name' => 'Demo Tenant',
        ]);

        // Crear usuario admin de prueba si no existe
        if (! User::where('name', 'admin')->exists()) {
            User::create([
                'name' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('Admin123!'),
                'tenant_id' => $tenant->id,
            ]);
            $this->command->info('Admin user created: admin / Admin123!');
        }

        $this->seedPricing($tenant);
        $this->seedClients($tenant);
        $this->seedFeatures($tenant);
        $this->seedCabins($tenant);
        $this->seedCabinFeaturesRelationships($tenant);

        $this->command->info('Demo data seeded successfully!');
    }

    /**
     * Seed pricing structure with groups and ranges for testing
     */
    private function seedPricing(Tenant $tenant): void
    {
        $this->command->info('Seeding Pricing...');

        // Crear solo 4 grupos de precios como se requiere
        $priceGroups = [
            [
                'name' => 'Temporada Baja',
                'price_per_night' => 100.00,
                'priority' => 0,
                'is_default' => true,  // Por defecto - se aplica a todas las fechas sin rango específico
            ],
            [
                'name' => 'Temporada Alta',
                'price_per_night' => 180.00,
                'priority' => 10,
                'is_default' => false,
            ],
            [
                'name' => 'Descuentos',
                'price_per_night' => 80.00,
                'priority' => 5,
                'is_default' => false,
            ],
            [
                'name' => 'Feriados',
                'price_per_night' => 250.00,
                'priority' => 20,  // Prioridad más alta
                'is_default' => false,
            ],
        ];

        $groupsCreated = [];
        foreach ($priceGroups as $groupData) {
            $groupData['tenant_id'] = $tenant->id;
            $group = PriceGroup::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $groupData['name']],
                $groupData
            );
            $groupsCreated[$group->name] = $group;
        }

        // Crear rangos de precios solo para algunos grupos
        // El grupo "Temporada Baja" NO tiene rangos porque es el por defecto
        $today = Carbon::now();

        $priceRanges = [
            // Temporada Alta: Diciembre-Enero-Febrero (verano)
            [
                'price_group' => 'Temporada Alta',
                'start_date' => $today->copy()->startOfYear()->addMonths(11)->format('Y-m-d'), // Diciembre
                'end_date' => $today->copy()->addYear()->startOfYear()->addMonths(1)->endOfMonth()->format('Y-m-d'), // Febrero año siguiente
            ],
            // Temporada Alta: Julio (vacaciones de invierno)
            [
                'price_group' => 'Temporada Alta',
                'start_date' => $today->copy()->startOfYear()->addMonths(6)->format('Y-m-d'), // Julio
                'end_date' => $today->copy()->startOfYear()->addMonths(6)->endOfMonth()->format('Y-m-d'),
            ],
            // Descuentos: Mayo-Junio (temporada baja turística)
            [
                'price_group' => 'Descuentos',
                'start_date' => $today->copy()->startOfYear()->addMonths(4)->format('Y-m-d'), // Mayo
                'end_date' => $today->copy()->startOfYear()->addMonths(5)->endOfMonth()->format('Y-m-d'), // Junio
            ],
            // Feriados específicos (ejemplo: fin de año)
            [
                'price_group' => 'Feriados',
                'start_date' => $today->copy()->endOfYear()->subDays(6)->format('Y-m-d'), // 25 dic
                'end_date' => $today->copy()->endOfYear()->addDays(2)->format('Y-m-d'), // 2 ene
            ],
        ];

        foreach ($priceRanges as $rangeData) {
            $priceGroup = $groupsCreated[$rangeData['price_group']] ?? null;
            if ($priceGroup) {
                PriceRange::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'price_group_id' => $priceGroup->id,
                        'start_date' => $rangeData['start_date'],
                        'end_date' => $rangeData['end_date'],
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'price_group_id' => $priceGroup->id,
                        'start_date' => $rangeData['start_date'],
                        'end_date' => $rangeData['end_date'],
                    ]
                );
            }
        }

        $this->command->line('  ✓ Pricing seeded: 4 price groups (Temporada Baja, Temporada Alta, Descuentos, Feriados)');
        $this->command->line('    • Temporada Baja: Por defecto (sin rangos de fecha) - Priority 0');
        $this->command->line('    • Descuentos: Mayo-Junio - Priority 5');
        $this->command->line('    • Temporada Alta: Diciembre-Febrero, Julio - Priority 10');
        $this->command->line('    • Feriados: Fin de año específico - Priority 20 (mayor prioridad)');
    }

    /**
     * Seed clients with different states for testing
     */
    private function seedClients(Tenant $tenant): void
    {
        $this->command->info('Seeding Clients...');

        // Clientes activos con datos completos (scenario: clientes normales)
        Client::factory(5)
            ->for($tenant)
            ->create();

        // Clientes activos con datos mínimos (scenario: sin contacto)
        Client::factory(3)
            ->for($tenant)
            ->minimalData()
            ->create();

        // Clientes eliminados (soft delete) - para test de recuperación
        Client::factory(2)
            ->for($tenant)
            ->deleted()
            ->create();

        // Cliente específico para tests (con email conocido)
        Client::factory()
            ->for($tenant)
            ->create([
                'name' => 'Juan Pérez',
                'dni' => '12345678',
                'email' => 'juan.perez@example.com',
                'phone' => '+54 9 11 1234-5678',
                'age' => 35,
                'city' => 'Buenos Aires',
            ]);

        // Cliente sin email (scenario: cliente sin datos de contacto)
        Client::factory()
            ->for($tenant)
            ->create([
                'name' => 'María García',
                'dni' => '87654321',
                'email' => null,
                'phone' => null,
                'age' => 28,
                'city' => 'Mendoza',
            ]);

        // Cliente eliminado y específico (scenario: cliente histórico eliminado)
        Client::factory()
            ->for($tenant)
            ->deleted()
            ->create([
                'name' => 'Cliente Eliminado',
                'dni' => '11111111',
                'email' => 'deleted@example.com',
            ]);

        $this->command->line('  ✓ Clients seeded: 13 total (11 active + 2 deleted)');
    }

    /**
     * Seed features with different states for testing
     */
    private function seedFeatures(Tenant $tenant): void
    {
        $this->command->info('Seeding Features...');

        // Features activas con datos completos (scenario: amenidades disponibles)
        Feature::factory(5)
            ->for($tenant)
            ->create();

        // Features activas con datos mínimos (scenario: sin ícono)
        Feature::factory(2)
            ->for($tenant)
            ->minimalData()
            ->create();

        // Features inactivas (scenario: amenidad temporalmente no disponible)
        Feature::factory(2)
            ->for($tenant)
            ->inactive()
            ->create();

        // Features eliminadas (soft delete) - para test de recuperación
        Feature::factory(1)
            ->for($tenant)
            ->deleted()
            ->create();

        // Features específicas populares
        $popularFeatures = [
            ['name' => 'Piscina', 'icon' => 'pool'],
            ['name' => 'WiFi', 'icon' => 'wifi'],
            ['name' => 'Aire Acondicionado', 'icon' => 'ac'],
            ['name' => 'Parrilla', 'icon' => 'grill'],
            ['name' => 'Cocina Equipada', 'icon' => 'kitchen'],
        ];

        foreach ($popularFeatures as $feature) {
            Feature::factory()
                ->for($tenant)
                ->create($feature);
        }

        // Feature inactiva y específica
        Feature::factory()
            ->for($tenant)
            ->inactive()
            ->create([
                'name' => 'Spa (En Mantenimiento)',
                'icon' => 'spa',
            ]);

        $this->command->line('  ✓ Features seeded: 16 total (13 active + 2 inactive + 1 deleted)');
    }

    /**
     * Seed cabins with different states for testing
     */
    private function seedCabins(Tenant $tenant): void
    {
        $this->command->info('Seeding Cabins...');

        // Cabañas activas con datos completos (scenario: cabañas disponibles)
        Cabin::factory(4)
            ->for($tenant)
            ->create();

        // Cabañas activas con datos mínimos (scenario: sin descripción)
        Cabin::factory(2)
            ->for($tenant)
            ->minimalData()
            ->create();

        // Cabañas inactivas (scenario: en mantenimiento o no disponible)
        Cabin::factory(2)
            ->for($tenant)
            ->inactive()
            ->create();

        // Cabañas inactivas y con datos mínimos
        Cabin::factory(1)
            ->for($tenant)
            ->inactive()
            ->minimalData()
            ->create();

        // Cabañas eliminadas (soft delete) - para test de recuperación
        Cabin::factory(1)
            ->for($tenant)
            ->deleted()
            ->create();

        // Cabaña eliminada e inactiva
        Cabin::factory(1)
            ->for($tenant)
            ->deleted()
            ->inactive()
            ->create();

        // Cabañas específicas populares (nombre conocido, descripción completa)
        $popularCabins = [
            [
                'name' => 'Cabaña del Bosque',
                'description' => 'Hermosa cabaña rodeada de árboles, perfecta para descansar en la naturaleza.',
                'capacity' => 4,
            ],
            [
                'name' => 'Cabaña del Lago',
                'description' => 'Con vista al lago, ideal para parejas o familias pequeñas.',
                'capacity' => 2,
            ],
            [
                'name' => 'Cabaña de Lujo',
                'description' => 'Completamente equipada con todas las comodidades, perfecta para grupos.',
                'capacity' => 8,
            ],
            [
                'name' => 'Cabaña Económica',
                'description' => 'Opción económica para viajeros con presupuesto ajustado.',
                'capacity' => 2,
            ],
        ];

        foreach ($popularCabins as $cabin) {
            Cabin::factory()
                ->for($tenant)
                ->create($cabin);
        }

        // Cabaña inactiva específica
        Cabin::factory()
            ->for($tenant)
            ->inactive()
            ->create([
                'name' => 'Cabaña en Reparación',
                'description' => 'Actualmente bajo mantenimiento, estará disponible pronto.',
                'capacity' => 4,
            ]);

        $this->command->line('  ✓ Cabins seeded: 16 total (9 active + 4 inactive + 3 deleted)');
    }

    /**
     * Seed relationships between cabins and features for testing
     */
    private function seedCabinFeaturesRelationships(Tenant $tenant): void
    {
        $this->command->info('Seeding Cabin-Feature Relationships...');

        $cabins = Cabin::where('tenant_id', $tenant->id)
            ->where('deleted_at', null)
            ->get();

        $features = Feature::where('tenant_id', $tenant->id)
            ->where('deleted_at', null)
            ->where('is_active', true)
            ->get();

        // Cada cabaña obtiene 2-5 features aleatorias
        foreach ($cabins as $cabin) {
            $randomFeatures = $features->random(rand(2, 5))->pluck('id');
            $cabin->features()->sync($randomFeatures);
        }

        $this->command->line('  ✓ Cabin-Feature relationships seeded');
    }
}
