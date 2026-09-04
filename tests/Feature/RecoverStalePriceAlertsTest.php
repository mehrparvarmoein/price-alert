<?php

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Domain\PriceAlert\Services\RecoverStalePriceAlerts;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;
use App\Models\User;

it('recovers a stale processing alert', function () {
    $alert = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
    ]);

    $recovery = app(RecoverStalePriceAlerts::class);

    $count = $recovery->execute();

    expect($count)->toBe(1);

    expect($alert->fresh()->status)->toBe(AlertStatus::ACTIVE);

    expect($alert->fresh()->processing_at)->toBeNull();
});


it('does not reactivate an alert with an existing delivery', function () {
    $alert = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
    ]);

    NotificationDelivery::factory()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
        'status' => NotificationDeliveryStatus::PENDING,
    ]);

    $recovery = app(RecoverStalePriceAlerts::class);

    expect($recovery->execute())->toBe(0);

    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);
});
