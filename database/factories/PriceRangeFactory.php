<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PriceGroup;
use App\Models\PriceRange;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceRange>
 */
class PriceRangeFactory extends Factory
{
    protected $model = PriceRange::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+6 months');
        $endDate = fake()->dateTimeBetween($startDate, $startDate->modify('+30 days'));

        return [
            'tenant_id' => Tenant::factory(),
            'price_group_id' => PriceGroup::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}

