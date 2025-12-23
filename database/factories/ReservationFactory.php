<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('now', '+3 months');
        $checkOut = fake()->dateTimeBetween($checkIn, $checkIn->modify('+7 days'));
        $totalPrice = fake()->randomFloat(2, 200, 2000);
        $depositAmount = round($totalPrice * 0.5, 2);

        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'cabin_id' => Cabin::factory(),
            'num_guests' => fake()->numberBetween(2, 6),
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'total_price' => $totalPrice,
            'deposit_amount' => $depositAmount,
            'balance_amount' => $totalPrice - $depositAmount,
            'status' => Reservation::STATUS_PENDING_CONFIRMATION,
            'pending_until' => now()->addHours(48),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Estado confirmada
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_CONFIRMED,
            'pending_until' => null,
        ]);
    }

    /**
     * Estado check-in realizado
     */
    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_CHECKED_IN,
            'pending_until' => null,
        ]);
    }

    /**
     * Estado finalizada
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_FINISHED,
            'pending_until' => null,
        ]);
    }

    /**
     * Estado cancelada
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_CANCELLED,
            'pending_until' => null,
        ]);
    }
}

