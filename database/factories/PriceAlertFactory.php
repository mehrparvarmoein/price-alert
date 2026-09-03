<?php

namespace Database\Factories;

use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Models\PriceAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceAlert>
 */
class PriceAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'target_price' => fake()->numberBetween(2000, 5000),
            'direction' => fake()->randomElement(AlertDirection::cases()),
            'status' => AlertStatus::ACTIVE,
            'processing_at' => null,
            'triggered_at' => null,
        ];
    }

    public function above(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => AlertDirection::ABOVE,
        ]);
    }

    public function below(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => AlertDirection::BELOW,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::ACTIVE,
            'processing_at' => null,
            'triggered_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::PROCESSING,
            'processing_at' => now(),
        ]);
    }

    public function triggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::TRIGGERED,
            'processing_at' => now()->subMinute(),
            'triggered_at' => now(),
        ]);
    }
}
