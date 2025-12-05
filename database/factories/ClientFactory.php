<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'dni' => fake()->unique()->numerify('########'),
            'age' => fake()->numberBetween(18, 80),
            'city' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional(0.7)->safeEmail(),
        ];
    }

    /**
     * Estado eliminado (soft delete)
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }

    /**
     * Datos mínimos requeridos (solo name y dni)
     */
    public function minimalData(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->name(),
            'dni' => fake()->unique()->numerify('########'),
        ]);
    }
}
