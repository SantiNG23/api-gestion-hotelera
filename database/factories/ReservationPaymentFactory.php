<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationPayment>
 */
class ReservationPaymentFactory extends Factory
{
    protected $model = ReservationPayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'amount' => fake()->randomFloat(2, 100, 1000),
            'payment_type' => ReservationPayment::TYPE_DEPOSIT,
            'payment_method' => fake()->randomElement(['efectivo', 'transferencia', 'tarjeta']),
            'paid_at' => now(),
        ];
    }

    /**
     * Pago de seña
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => ReservationPayment::TYPE_DEPOSIT,
        ]);
    }

    /**
     * Pago de saldo
     */
    public function balance(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => ReservationPayment::TYPE_BALANCE,
        ]);
    }
}

