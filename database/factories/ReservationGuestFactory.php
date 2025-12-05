<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationGuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationGuest>
 */
class ReservationGuestFactory extends Factory
{
    protected $model = ReservationGuest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'name' => fake()->name(),
            'dni' => fake()->numerify('########'),
            'age' => fake()->optional(0.7)->numberBetween(1, 90),
            'city' => fake()->optional(0.5)->city(),
            'phone' => fake()->optional(0.3)->phoneNumber(),
            'email' => fake()->optional(0.3)->safeEmail(),
        ];
    }
}

