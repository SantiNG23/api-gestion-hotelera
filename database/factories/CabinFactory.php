<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cabin;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cabin>
 */
class CabinFactory extends Factory
{
    protected $model = Cabin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Cabaña ' . fake()->randomElement(['del Bosque', 'del Lago', 'del Sol', 'de la Montaña', 'del Valle']),
            'description' => fake()->paragraph(),
            'capacity' => fake()->numberBetween(2, 8),
            'is_active' => true,
        ];
    }

    /**
     * Estado inactivo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

