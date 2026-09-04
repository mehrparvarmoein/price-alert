<?php

namespace Database\Factories;

use App\Models\OutboxMessage;
use App\Models\PriceAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboxMessage>
 */
class OutboxMessageFactory extends Factory
{
    protected $model = OutboxMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'price_alert.notification_requested',
            'aggregate_type' => PriceAlert::class,
            'aggregate_id' => fake()->numberBetween(1, 1000),
            'payload' => ['foo' => 'bar'],
            'processed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => null,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processed_at' => now(),
        ]);
    }

    public function notificationRequested(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'price_alert.notification_requested',
            'aggregate_type' => PriceAlert::class,
        ]);
    }

    public function forType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    public function forAggregate(string $aggregateType, int $aggregateId): static
    {
        return $this->state(fn (array $attributes) => [
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
        ]);
    }
}
