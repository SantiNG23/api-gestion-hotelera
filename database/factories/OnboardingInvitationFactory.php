<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OnboardingInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OnboardingInvitation>
 */
class OnboardingInvitationFactory extends Factory
{
    protected $model = OnboardingInvitation::class;

    public function definition(): array
    {
        $prefillName = fake()->company();

        return [
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHours(72),
            'consumed_at' => null,
            'revoked_at' => null,
            'tenant_name_prefill' => $prefillName,
            'tenant_slug_prefill' => Str::slug($prefillName),
            'created_by' => null,
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->addHours(72),
            'consumed_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->addHours(72),
            'consumed_at' => now()->subMinute(),
            'revoked_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
            'consumed_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->addHours(72),
            'consumed_at' => null,
            'revoked_at' => now()->subMinute(),
        ]);
    }
}
