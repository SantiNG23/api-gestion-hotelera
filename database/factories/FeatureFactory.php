<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Feature;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $features = [
            ['name' => 'Pileta', 'icon' => 'pool'],
            ['name' => 'Cochera', 'icon' => 'garage'],
            ['name' => 'WiFi', 'icon' => 'wifi'],
            ['name' => 'Aire Acondicionado', 'icon' => 'ac'],
            ['name' => 'Parrilla', 'icon' => 'grill'],
            ['name' => 'TV', 'icon' => 'tv'],
            ['name' => 'Cocina Equipada', 'icon' => 'kitchen'],
            ['name' => 'Ropa de Cama', 'icon' => 'bed'],
        ];

        $feature = fake()->randomElement($features);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $feature['name'],
            'icon' => $feature['icon'],
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

