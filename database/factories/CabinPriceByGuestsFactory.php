<?php

namespace Database\Factories;

use App\Models\Cabin;
use App\Models\CabinPriceByGuests;
use App\Models\PriceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CabinPriceByGuests>
 */
class CabinPriceByGuestsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cabin_id' => Cabin::factory(),
            'price_group_id' => PriceGroup::factory(),
            'num_guests' => $this->faker->numberBetween(2, 8),
            'price_per_night' => $this->faker->randomFloat(2, 50, 500),
        ];
    }
}
