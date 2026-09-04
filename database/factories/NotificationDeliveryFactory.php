<?php

namespace Database\Factories;

use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => PriceAlert::factory(),
            'idempotency_key' => Str::uuid()->toString(),
            'status' => NotificationDeliveryStatus::PENDING,
            'sent_at' => null,
            'failed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDeliveryStatus::PENDING,
            'sent_at' => null,
            'failed_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDeliveryStatus::SENT,
            'sent_at' => now(),
            'failed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NotificationDeliveryStatus::FAILED,
            'sent_at' => null,
            'failed_at' => now(),
        ]);
    }
}
