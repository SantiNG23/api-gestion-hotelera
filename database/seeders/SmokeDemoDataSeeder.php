<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\Client;
use App\Models\Feature;
use App\Models\PriceGroup;
use App\Models\PriceRange;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReservationService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SmokeDemoDataSeeder extends Seeder
{
    private const PASSWORD = 'Demo123!';

    private const SHARED_LOOKUP_DNI = '41000001';

    private const SUMMARY_DATE = '2030-04-10';

    private const SMOKE_NOTE_PREFIX = '[SMOKE:';

    public function run(): void
    {
        $this->command?->info('Seeding deterministic smoke demo data...');

        foreach ($this->tenantBlueprints() as $tenantBlueprint) {
            $tenant = Tenant::query()->updateOrCreate(
                ['slug' => $tenantBlueprint['slug']],
                [
                    'name' => $tenantBlueprint['name'],
                    'is_active' => true,
                ]
            );

            app(TenantContext::class)->run($tenant->id, function () use ($tenant, $tenantBlueprint): void {
                foreach ($tenantBlueprint['users'] as $userBlueprint) {
                    $this->seedUser($tenant, $userBlueprint);
                }

                $features = $this->seedFeatures($tenantBlueprint['features']);
                $cabins = $this->seedCabins($tenantBlueprint['cabins'], $features);
                $priceGroups = $this->seedPricing($tenantBlueprint['price_groups']);

                $this->seedCabinPrices($cabins, $priceGroups, $tenantBlueprint['price_multipliers']);

                $clients = $this->seedClients($tenantBlueprint['clients']);

                $this->resetSmokeReservations();
                $this->seedReservations($tenantBlueprint['reservations'], $cabins, $clients);
            });

            $this->command?->line(sprintf(
                '  - %s listo (%s / %s)',
                $tenant->name,
                $tenantBlueprint['users'][0]['email'],
                self::PASSWORD
            ));
        }

        $this->command?->info('Smoke demo data seeded successfully.');
    }

    private function seedUser(Tenant $tenant, array $userBlueprint): void
    {
        User::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $userBlueprint['email'],
            ],
            [
                'name' => $userBlueprint['name'],
                'password' => Hash::make(self::PASSWORD),
                'tenant_id' => $tenant->id,
            ]
        );
    }

    private function seedFeatures(array $featureBlueprints): array
    {
        $features = [];
        $tenantId = app(TenantContext::class)->requireId();

        foreach ($featureBlueprints as $featureBlueprint) {
            /** @var Feature $feature */
            $feature = Feature::withTrashed()->firstOrNew([
                'tenant_id' => $tenantId,
                'name' => $featureBlueprint['name'],
            ]);

            $feature->fill([
                'tenant_id' => $tenantId,
                'icon' => $featureBlueprint['icon'],
                'is_active' => true,
            ]);
            $feature->save();

            if ($featureBlueprint['deleted'] ?? false) {
                if (! $feature->trashed()) {
                    $feature->delete();
                }
            } elseif ($feature->trashed()) {
                $feature->restore();
            }

            $features[$featureBlueprint['key']] = $feature->fresh();
        }

        return $features;
    }

    private function seedCabins(array $cabinBlueprints, array $features): array
    {
        $cabins = [];
        $tenantId = app(TenantContext::class)->requireId();

        foreach ($cabinBlueprints as $cabinBlueprint) {
            /** @var Cabin $cabin */
            $cabin = Cabin::withTrashed()->firstOrNew([
                'tenant_id' => $tenantId,
                'name' => $cabinBlueprint['name'],
            ]);

            $cabin->fill([
                'tenant_id' => $tenantId,
                'description' => $cabinBlueprint['description'],
                'capacity' => $cabinBlueprint['capacity'],
                'is_active' => $cabinBlueprint['is_active'] ?? true,
            ]);
            $cabin->save();

            if ($cabinBlueprint['deleted'] ?? false) {
                if (! $cabin->trashed()) {
                    $cabin->delete();
                }
            } elseif ($cabin->trashed()) {
                $cabin->restore();
            }

            $cabin->features()->sync(
                collect($cabinBlueprint['feature_keys'])
                    ->map(fn (string $featureKey): int => $features[$featureKey]->id)
                    ->all()
            );

            $cabins[$cabinBlueprint['key']] = $cabin->fresh(['features']);
        }

        return $cabins;
    }

    private function seedPricing(array $priceGroupBlueprints): array
    {
        $priceGroups = [];
        $tenantId = app(TenantContext::class)->requireId();

        foreach ($priceGroupBlueprints as $priceGroupBlueprint) {
            $priceGroup = PriceGroup::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $priceGroupBlueprint['name'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'price_per_night' => $priceGroupBlueprint['base_price'],
                    'priority' => $priceGroupBlueprint['priority'],
                    'is_default' => $priceGroupBlueprint['is_default'],
                ]
            );

            $priceGroups[$priceGroupBlueprint['key']] = $priceGroup;

            foreach ($priceGroupBlueprint['ranges'] as $rangeBlueprint) {
                PriceRange::query()->updateOrCreate(
                    [
                        'price_group_id' => $priceGroup->id,
                        'tenant_id' => $tenantId,
                        'start_date' => $rangeBlueprint['start_date'],
                        'end_date' => $rangeBlueprint['end_date'],
                    ],
                    [
                        'tenant_id' => $tenantId,
                    ]
                );
            }
        }

        return $priceGroups;
    }

    private function seedCabinPrices(array $cabins, array $priceGroups, array $priceMultipliers): void
    {
        $tenantId = app(TenantContext::class)->requireId();

        foreach ($cabins as $cabinKey => $cabin) {
            foreach ($priceGroups as $priceGroupKey => $priceGroup) {
                $multiplier = $priceMultipliers[$cabinKey][$priceGroupKey] ?? 1;

                for ($guestCount = 1; $guestCount <= $cabin->capacity; $guestCount++) {
                    CabinPriceByGuests::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'cabin_id' => $cabin->id,
                            'price_group_id' => $priceGroup->id,
                            'num_guests' => $guestCount,
                        ],
                        [
                            'tenant_id' => $tenantId,
                            'price_per_night' => $priceGroup->price_per_night * $guestCount * $multiplier,
                        ]
                    );
                }
            }
        }
    }

    private function seedClients(array $clientBlueprints): array
    {
        $clients = [];
        $tenantId = app(TenantContext::class)->requireId();

        foreach ($clientBlueprints as $clientBlueprint) {
            /** @var Client $client */
            $client = Client::withTrashed()->firstOrNew([
                'tenant_id' => $tenantId,
                'dni' => $clientBlueprint['dni'],
            ]);

            $client->fill([
                'tenant_id' => $tenantId,
                'name' => $clientBlueprint['name'],
                'age' => $clientBlueprint['age'],
                'city' => $clientBlueprint['city'],
                'phone' => $clientBlueprint['phone'],
                'email' => $clientBlueprint['email'],
            ]);
            $client->save();

            if ($clientBlueprint['deleted'] ?? false) {
                if (! $client->trashed()) {
                    $client->delete();
                }
            } elseif ($client->trashed()) {
                $client->restore();
            }

            $clients[$clientBlueprint['key']] = $client->fresh();
        }

        return $clients;
    }

    private function resetSmokeReservations(): void
    {
        Reservation::query()
            ->where('notes', 'like', self::SMOKE_NOTE_PREFIX.'%')
            ->get()
            ->each(function (Reservation $reservation): void {
                $reservation->forceDelete();
            });
    }

    private function seedReservations(array $reservationBlueprints, array $cabins, array $clients): void
    {
        /** @var ReservationService $reservationService */
        $reservationService = app(ReservationService::class);

        foreach ($reservationBlueprints as $reservationBlueprint) {
            $reservation = $reservationService->createReservation([
                'cabin_id' => $cabins[$reservationBlueprint['cabin_key']]->id,
                'check_in_date' => $reservationBlueprint['check_in_date'],
                'check_out_date' => $reservationBlueprint['check_out_date'],
                'num_guests' => $reservationBlueprint['num_guests'],
                'pending_hours' => $reservationBlueprint['pending_hours'] ?? 48,
                'notes' => $reservationBlueprint['notes'],
                'is_blocked' => $reservationBlueprint['is_blocked'] ?? false,
                'client' => [
                    'name' => $clients[$reservationBlueprint['client_key']]->name,
                    'dni' => $clients[$reservationBlueprint['client_key']]->dni,
                    'age' => $clients[$reservationBlueprint['client_key']]->age,
                    'city' => $clients[$reservationBlueprint['client_key']]->city,
                    'phone' => $clients[$reservationBlueprint['client_key']]->phone,
                    'email' => $clients[$reservationBlueprint['client_key']]->email,
                ],
                'guests' => $reservationBlueprint['guests'] ?? [],
            ]);

            if (isset($reservationBlueprint['pending_until'])) {
                $reservation->update(['pending_until' => $reservationBlueprint['pending_until']]);
            }

            if (($reservationBlueprint['confirm'] ?? false) === true) {
                $reservation = $reservationService->confirm($reservation->id, [
                    'payment_method' => 'transferencia',
                    'paid_at' => $reservationBlueprint['deposit_paid_at'] ?? $reservationBlueprint['check_in_date'].' 10:00:00',
                ]);
            }

            if (($reservationBlueprint['pay_balance'] ?? false) === true) {
                $reservation = $reservationService->payBalance($reservation->id, [
                    'payment_method' => 'transferencia',
                    'paid_at' => $reservationBlueprint['balance_paid_at'] ?? $reservationBlueprint['check_in_date'].' 12:00:00',
                ]);
            }

            if (($reservationBlueprint['check_in'] ?? false) === true) {
                $reservation = $reservationService->checkIn($reservation->id, [
                    'payment_method' => 'efectivo',
                    'paid_at' => $reservationBlueprint['balance_paid_at'] ?? $reservationBlueprint['check_in_date'].' 14:00:00',
                ]);
            }

            if (($reservationBlueprint['check_out'] ?? false) === true) {
                $reservation = $reservationService->checkOut($reservation->id);
            }

            if (($reservationBlueprint['cancel'] ?? false) === true) {
                $reservation = $reservationService->cancel($reservation->id);
            }

            if (($reservationBlueprint['delete_client_after'] ?? false) === true) {
                $reservation->client()->withTrashed()->first()?->delete();
            }

            if (($reservationBlueprint['delete_cabin_after'] ?? false) === true) {
                $reservation->cabin()->withTrashed()->first()?->delete();
            }
        }
    }

    private function tenantBlueprints(): array
    {
        return [
            [
                'slug' => 'smoke-sierra-clara',
                'name' => 'Smoke Sierra Clara',
                'users' => [
                    [
                        'name' => 'Operador Smoke Sierra',
                        'email' => 'smoke.sierra@miradordeluz.test',
                    ],
                    [
                        'name' => 'Operador Compartido Sierra',
                        'email' => 'smoke.shared@miradordeluz.test',
                    ],
                ],
                'features' => [
                    ['key' => 'wifi', 'name' => 'SMOKE A | Wifi', 'icon' => 'wifi'],
                    ['key' => 'parrilla', 'name' => 'SMOKE A | Parrilla', 'icon' => 'grill'],
                    ['key' => 'jacuzzi', 'name' => 'SMOKE A | Jacuzzi', 'icon' => 'spa'],
                    ['key' => 'cochera', 'name' => 'SMOKE A | Cochera', 'icon' => 'garage'],
                ],
                'cabins' => [
                    [
                        'key' => 'alerce',
                        'name' => 'SMOKE A | Alerce Familiar',
                        'description' => 'Cabana base para smoke de reservas y pricing.',
                        'capacity' => 6,
                        'feature_keys' => ['wifi', 'parrilla', 'cochera'],
                    ],
                    [
                        'key' => 'cipres',
                        'name' => 'SMOKE A | Cipres Pareja',
                        'description' => 'Cabana para smoke de checkout y disponibilidad.',
                        'capacity' => 2,
                        'feature_keys' => ['wifi', 'jacuzzi'],
                    ],
                    [
                        'key' => 'coihue',
                        'name' => 'SMOKE A | Coihue Grupo',
                        'description' => 'Cabana para smoke de pendientes, bloqueos y calendar.',
                        'capacity' => 4,
                        'feature_keys' => ['wifi', 'parrilla', 'jacuzzi'],
                    ],
                    [
                        'key' => 'sauce',
                        'name' => 'SMOKE A | Sauce Historial',
                        'description' => 'Cabana activa para historial de cliente y bajas logicas.',
                        'capacity' => 5,
                        'feature_keys' => ['wifi', 'cochera'],
                    ],
                    [
                        'key' => 'arrayan',
                        'name' => 'SMOKE A | Arrayan Archivada',
                        'description' => 'Cabana archivada para validar relaciones historicas con soft delete.',
                        'capacity' => 3,
                        'feature_keys' => ['wifi', 'jacuzzi'],
                    ],
                ],
                'price_groups' => [
                    [
                        'key' => 'base',
                        'name' => 'SMOKE A | Base',
                        'base_price' => 100,
                        'priority' => 0,
                        'is_default' => true,
                        'ranges' => [],
                    ],
                    [
                        'key' => 'promo_otono',
                        'name' => 'SMOKE A | Promo Otono',
                        'base_price' => 90,
                        'priority' => 5,
                        'is_default' => false,
                        'ranges' => [
                            ['start_date' => '2030-05-01', 'end_date' => '2030-05-31'],
                        ],
                    ],
                    [
                        'key' => 'invierno',
                        'name' => 'SMOKE A | Invierno',
                        'base_price' => 130,
                        'priority' => 10,
                        'is_default' => false,
                        'ranges' => [
                            ['start_date' => '2030-07-06', 'end_date' => '2030-07-31'],
                        ],
                    ],
                    [
                        'key' => 'feriado',
                        'name' => 'SMOKE A | Feriado Largo',
                        'base_price' => 180,
                        'priority' => 20,
                        'is_default' => false,
                        'ranges' => [
                            ['start_date' => '2030-04-09', 'end_date' => '2030-04-12'],
                        ],
                    ],
                ],
                'price_multipliers' => [
                    'alerce' => ['base' => 1.2, 'promo_otono' => 1.2, 'invierno' => 1.2, 'feriado' => 1.2],
                    'cipres' => ['base' => 1.0, 'promo_otono' => 1.0, 'invierno' => 1.0, 'feriado' => 1.0],
                    'coihue' => ['base' => 1.1, 'promo_otono' => 1.1, 'invierno' => 1.1, 'feriado' => 1.1],
                ],
                'clients' => [
                    [
                        'key' => 'lookup',
                        'name' => 'SMOKE Cliente Lookup A',
                        'dni' => self::SHARED_LOOKUP_DNI,
                        'age' => 35,
                        'city' => 'Bariloche',
                        'phone' => '2944123456',
                        'email' => 'lookup.a@miradordeluz.test',
                    ],
                    [
                        'key' => 'reserva_base',
                        'name' => 'SMOKE Reserva Base A',
                        'dni' => '41000011',
                        'age' => 41,
                        'city' => 'Neuquen',
                        'phone' => '2994123401',
                        'email' => 'reserva.base.a@miradordeluz.test',
                    ],
                    [
                        'key' => 'checkout',
                        'name' => 'SMOKE Checkout A',
                        'dni' => '41000012',
                        'age' => 29,
                        'city' => 'Mendoza',
                        'phone' => '2614123402',
                        'email' => 'checkout.a@miradordeluz.test',
                    ],
                    [
                        'key' => 'pendiente',
                        'name' => 'SMOKE Pendiente A',
                        'dni' => '41000013',
                        'age' => 33,
                        'city' => 'Cordoba',
                        'phone' => '3514123403',
                        'email' => 'pendiente.a@miradordeluz.test',
                    ],
                    [
                        'key' => 'historial',
                        'name' => 'SMOKE Historial A',
                        'dni' => '41000014',
                        'age' => 46,
                        'city' => 'San Rafael',
                        'phone' => '2604123404',
                        'email' => 'historial.a@miradordeluz.test',
                    ],
                    [
                        'key' => 'archivado',
                        'name' => 'SMOKE Archivado A',
                        'dni' => '41000015',
                        'age' => 52,
                        'city' => 'Trelew',
                        'phone' => '2804123405',
                        'email' => 'archivado.a@miradordeluz.test',
                    ],
                    [
                        'key' => 'cliente_baja',
                        'name' => 'SMOKE Cliente Baja A',
                        'dni' => '41000016',
                        'age' => 31,
                        'city' => 'Esquel',
                        'phone' => '2945412340',
                        'email' => 'cliente.baja.a@miradordeluz.test',
                    ],
                ],
                'reservations' => [
                    [
                        'client_key' => 'reserva_base',
                        'cabin_key' => 'alerce',
                        'check_in_date' => self::SUMMARY_DATE,
                        'check_out_date' => '2030-04-13',
                        'num_guests' => 4,
                        'notes' => '[SMOKE:A:CONFIRMED_CHECKIN]',
                        'confirm' => true,
                        'deposit_paid_at' => '2030-04-01 10:00:00',
                        'guests' => [
                            [
                                'name' => 'SMOKE Huesped Extra A1',
                                'dni' => '51000001',
                                'age' => 31,
                                'city' => 'Neuquen',
                            ],
                        ],
                    ],
                    [
                        'client_key' => 'checkout',
                        'cabin_key' => 'cipres',
                        'check_in_date' => '2030-04-07',
                        'check_out_date' => self::SUMMARY_DATE,
                        'num_guests' => 2,
                        'notes' => '[SMOKE:A:CHECKOUT]',
                        'confirm' => true,
                        'pay_balance' => true,
                        'check_in' => true,
                        'deposit_paid_at' => '2030-04-05 09:00:00',
                        'balance_paid_at' => '2030-04-07 14:00:00',
                    ],
                    [
                        'client_key' => 'pendiente',
                        'cabin_key' => 'coihue',
                        'check_in_date' => self::SUMMARY_DATE,
                        'check_out_date' => '2030-04-12',
                        'num_guests' => 3,
                        'notes' => '[SMOKE:A:PENDING_SUMMARY]',
                        'pending_until' => '2030-04-10 18:00:00',
                    ],
                    [
                        'client_key' => 'pendiente',
                        'cabin_key' => 'coihue',
                        'check_in_date' => '2030-04-15',
                        'check_out_date' => '2030-04-18',
                        'num_guests' => 1,
                        'notes' => '[SMOKE:A:BLOCKED]',
                        'is_blocked' => true,
                    ],
                    [
                        'client_key' => 'historial',
                        'cabin_key' => 'sauce',
                        'check_in_date' => '2030-03-20',
                        'check_out_date' => '2030-03-23',
                        'num_guests' => 3,
                        'notes' => '[SMOKE:A:HISTORY_FINISHED]',
                        'confirm' => true,
                        'pay_balance' => true,
                        'check_in' => true,
                        'check_out' => true,
                        'deposit_paid_at' => '2030-03-18 09:30:00',
                        'balance_paid_at' => '2030-03-20 14:00:00',
                        'guests' => [
                            [
                                'name' => 'SMOKE Historial Guest A1',
                                'dni' => '51000002',
                                'age' => 28,
                                'city' => 'San Rafael',
                            ],
                            [
                                'name' => 'SMOKE Historial Guest A2',
                                'dni' => '51000003',
                                'age' => 27,
                                'city' => 'San Rafael',
                            ],
                        ],
                    ],
                    [
                        'client_key' => 'historial',
                        'cabin_key' => 'alerce',
                        'check_in_date' => '2030-06-01',
                        'check_out_date' => '2030-06-04',
                        'num_guests' => 2,
                        'notes' => '[SMOKE:A:HISTORY_CANCELLED]',
                        'confirm' => true,
                        'cancel' => true,
                        'deposit_paid_at' => '2030-05-20 10:15:00',
                    ],
                    [
                        'client_key' => 'historial',
                        'cabin_key' => 'coihue',
                        'check_in_date' => '2030-04-22',
                        'check_out_date' => '2030-04-24',
                        'num_guests' => 2,
                        'notes' => '[SMOKE:A:HISTORY_CONFIRMED]',
                        'confirm' => true,
                        'deposit_paid_at' => '2030-04-18 16:00:00',
                    ],
                    [
                        'client_key' => 'pendiente',
                        'cabin_key' => 'coihue',
                        'check_in_date' => '2030-04-25',
                        'check_out_date' => '2030-04-27',
                        'num_guests' => 2,
                        'notes' => '[SMOKE:A:PENDING_EXPIRED]',
                        'pending_until' => now()->subDay()->format('Y-m-d H:i:s'),
                    ],
                    [
                        'client_key' => 'archivado',
                        'cabin_key' => 'arrayan',
                        'check_in_date' => '2030-02-10',
                        'check_out_date' => '2030-02-12',
                        'num_guests' => 2,
                        'notes' => '[SMOKE:A:ARCHIVED_RELATIONS]',
                        'confirm' => true,
                        'pay_balance' => true,
                        'check_in' => true,
                        'check_out' => true,
                        'deposit_paid_at' => '2030-02-01 09:00:00',
                        'balance_paid_at' => '2030-02-10 12:00:00',
                        'delete_client_after' => true,
                        'delete_cabin_after' => true,
                    ],
                    [
                        'client_key' => 'cliente_baja',
                        'cabin_key' => 'sauce',
                        'check_in_date' => '2030-08-10',
                        'check_out_date' => '2030-08-12',
                        'num_guests' => 2,
                        'notes' => '[SMOKE:A:CLIENT_DELETE_TARGET]',
                        'confirm' => true,
                        'deposit_paid_at' => '2030-08-01 11:00:00',
                    ],
                ],
            ],
            [
                'slug' => 'smoke-bosque-sereno',
                'name' => 'Smoke Bosque Sereno',
                'users' => [
                    [
                        'name' => 'Operador Smoke Bosque',
                        'email' => 'smoke.bosque@miradordeluz.test',
                    ],
                    [
                        'name' => 'Operador Compartido Bosque',
                        'email' => 'smoke.shared@miradordeluz.test',
                    ],
                ],
                'features' => [
                    ['key' => 'wifi', 'name' => 'SMOKE B | Wifi', 'icon' => 'wifi'],
                    ['key' => 'pileta', 'name' => 'SMOKE B | Pileta', 'icon' => 'pool'],
                    ['key' => 'hogar', 'name' => 'SMOKE B | Hogar a lena', 'icon' => 'fire'],
                ],
                'cabins' => [
                    [
                        'key' => 'radal',
                        'name' => 'SMOKE B | Radal Vista',
                        'description' => 'Cabana equivalente para smoke cross-tenant.',
                        'capacity' => 4,
                        'feature_keys' => ['wifi', 'pileta'],
                    ],
                    [
                        'key' => 'lenga',
                        'name' => 'SMOKE B | Lenga Norte',
                        'description' => 'Cabana auxiliar del tenant secundario.',
                        'capacity' => 2,
                        'feature_keys' => ['wifi', 'hogar'],
                    ],
                ],
                'price_groups' => [
                    [
                        'key' => 'base',
                        'name' => 'SMOKE B | Base',
                        'base_price' => 95,
                        'priority' => 0,
                        'is_default' => true,
                        'ranges' => [],
                    ],
                    [
                        'key' => 'feriado',
                        'name' => 'SMOKE B | Feriado Largo',
                        'base_price' => 170,
                        'priority' => 20,
                        'is_default' => false,
                        'ranges' => [
                            ['start_date' => '2030-04-09', 'end_date' => '2030-04-12'],
                        ],
                    ],
                ],
                'price_multipliers' => [
                    'radal' => ['base' => 1.0, 'feriado' => 1.0],
                    'lenga' => ['base' => 0.95, 'feriado' => 0.95],
                ],
                'clients' => [
                    [
                        'key' => 'lookup',
                        'name' => 'SMOKE Cliente Lookup B',
                        'dni' => self::SHARED_LOOKUP_DNI,
                        'age' => 39,
                        'city' => 'San Martin de los Andes',
                        'phone' => '2944412345',
                        'email' => 'lookup.b@miradordeluz.test',
                    ],
                    [
                        'key' => 'reserva_base',
                        'name' => 'SMOKE Reserva Base B',
                        'dni' => '42000011',
                        'age' => 37,
                        'city' => 'Villa La Angostura',
                        'phone' => '2944432101',
                        'email' => 'reserva.base.b@miradordeluz.test',
                    ],
                ],
                'reservations' => [
                    [
                        'client_key' => 'reserva_base',
                        'cabin_key' => 'radal',
                        'check_in_date' => '2030-04-11',
                        'check_out_date' => '2030-04-14',
                        'num_guests' => 2,
                        'notes' => '[SMOKE:B:CONFIRMED]',
                        'confirm' => true,
                        'deposit_paid_at' => '2030-04-02 11:00:00',
                    ],
                ],
            ],
        ];
    }
}
