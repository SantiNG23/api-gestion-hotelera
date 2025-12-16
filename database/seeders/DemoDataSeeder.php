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

        // Crear grupos de precios
        $priceGroups = [
            [
                'name' => 'Temporada Baja',
                'price_per_night' => 80.00,
                'priority' => 1,
                'is_default' => false,
            ],
            [
                'name' => 'Temporada Media',
                'price_per_night' => 120.00,
                'priority' => 2,
                'is_default' => false,
            ],
            [
                'name' => 'Temporada Alta',
                'price_per_night' => 180.00,
                'priority' => 3,
                'is_default' => false,
            ],
            [
                'name' => 'Fin de Semana Largo',
                'price_per_night' => 200.00,
                'priority' => 4,
                'is_default' => false,
            ],
            [
                'name' => 'Fiestas y Vacaciones',
                'price_per_night' => 300.00,
                'priority' => 5,
                'is_default' => false,
            ],
            [
                'name' => 'Tarifa por Defecto',
                'price_per_night' => 100.00,
                'priority' => 0,
                'is_default' => true,
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

        // Crear rangos de precios (cubren varios meses para pruebas realistas)
        $today = Carbon::now();

        // Rangos de ejemplo que cubren diferentes períodos del año
        $priceRanges = [
            // Temporada Media: enero, febrero, marzo (veranos moderados)
            [
                'price_group' => 'Temporada Media',
                'start_date' => $today->copy()->startOfYear()->format('Y-m-d'),
                'end_date' => $today->copy()->startOfYear()->addMonths(2)->endOfMonth()->format('Y-m-d'),
            ],
            // Temporada Baja: abril-junio (invierno)
            [
                'price_group' => 'Temporada Baja',
                'start_date' => $today->copy()->startOfYear()->addMonths(3)->format('Y-m-d'),
                'end_date' => $today->copy()->startOfYear()->addMonths(5)->endOfMonth()->format('Y-m-d'),
            ],
            // Temporada Media: julio-agosto (invierno moderado)
            [
                'price_group' => 'Temporada Media',
                'start_date' => $today->copy()->startOfYear()->addMonths(6)->format('Y-m-d'),
                'end_date' => $today->copy()->startOfYear()->addMonths(7)->endOfMonth()->format('Y-m-d'),
            ],
            // Temporada Alta: septiembre-noviembre (primavera/verano temprano)
            [
                'price_group' => 'Temporada Alta',
                'start_date' => $today->copy()->startOfYear()->addMonths(8)->format('Y-m-d'),
                'end_date' => $today->copy()->startOfYear()->addMonths(10)->endOfMonth()->format('Y-m-d'),
            ],
            // Fiestas: diciembre (navidad y año nuevo)
            [
                'price_group' => 'Fiestas y Vacaciones',
                'start_date' => $today->copy()->startOfYear()->addMonths(11)->format('Y-m-d'),
                'end_date' => $today->copy()->endOfYear()->format('Y-m-d'),
            ],
            // Rango futuro: próximos 6 meses desde hoy
            [
                'price_group' => 'Temporada Media',
                'start_date' => $today->copy()->addMonths(1)->format('Y-m-d'),
                'end_date' => $today->copy()->addMonths(3)->format('Y-m-d'),
            ],
            // Fin de semana largo próximo (si existe)
            [
                'price_group' => 'Fin de Semana Largo',
                'start_date' => $today->copy()->addDays(7)->format('Y-m-d'),
                'end_date' => $today->copy()->addDays(10)->format('Y-m-d'),
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

        $this->command->line('  ✓ Pricing seeded: 6 price groups + multiple price ranges');
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
