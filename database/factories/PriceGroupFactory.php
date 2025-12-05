<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PriceGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceGroup>
 */
class PriceGroupFactory extends Factory
{
    protected $model = PriceGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $groups = [
            ['name' => 'Temporada Baja', 'price' => fake()->randomFloat(2, 50, 100)],
            ['name' => 'Temporada Media', 'price' => fake()->randomFloat(2, 100, 150)],
            ['name' => 'Temporada Alta', 'price' => fake()->randomFloat(2, 150, 250)],
            ['name' => 'Fin de Semana Largo', 'price' => fake()->randomFloat(2, 180, 300)],
            ['name' => 'Fiestas', 'price' => fake()->randomFloat(2, 250, 400)],
        ];

        $group = fake()->randomElement($groups);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $group['name'],
            'price_per_night' => $group['price'],
            'is_default' => false,
        ];
    }

    /**
     * Estado default
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}

