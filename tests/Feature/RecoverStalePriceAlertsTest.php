<?php

use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Domain\PriceAlert\Services\RecoverStalePriceAlerts;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::del(['price_alerts:above', 'price_alerts:below']);
});

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


it('does not reactivate an alert with an existing SENT delivery', function () {
    $alert = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
    ]);

    NotificationDelivery::factory()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
        'status' => NotificationDeliveryStatus::SENT,
    ]);

    $recovery = app(RecoverStalePriceAlerts::class);

    expect($recovery->execute())->toBe(0);

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
});

it('FAILED delivery recovery unsticks alert and deletes delivery', function () {
    $alert = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
    ]);

    NotificationDelivery::factory()->failed()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
    ]);

    $recovery = app(RecoverStalePriceAlerts::class);
    $count = $recovery->execute();

    expect($count)->toBe(1);
    expect($alert->fresh()->status)->toBe(AlertStatus::ACTIVE);
    expect($alert->fresh()->processing_at)->toBeNull();
    expect(NotificationDelivery::where('alert_id', $alert->id)->exists())->toBeFalse();
});

it('SENDING/PENDING stale delivery is reset and re-dispatched', function () {
    Queue::fake();

    $alert = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
    ]);

    $delivery = NotificationDelivery::factory()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
        'status' => NotificationDeliveryStatus::SENDING,
        'sending_at' => now()->subMinutes(10),
    ]);

    app(RecoverStalePriceAlerts::class)->execute();

    expect($delivery->fresh()->status)->toBe(NotificationDeliveryStatus::PENDING);
    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);
    Queue::assertPushed(SendPriceAlertNotificationJob::class, 1);

    // PENDING stale does not reset to ACTIVE but stays PROCESSING;
    // it will be retried via the dispatched job or eventually become FAILED and then reindexed
    $alert2 = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
    ]);

    NotificationDelivery::factory()->pending()->create([
        'alert_id' => $alert2->id,
        'idempotency_key' => "price-alert:{$alert2->id}",
    ]);

    Queue::fake();
    expect(app(RecoverStalePriceAlerts::class)->execute())->toBe(0);
    expect($alert2->fresh()->status)->toBe(AlertStatus::PROCESSING);
});

it('recovered stale alert is reindexed in Redis', function () {
    $alert = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
        'target_price' => 3450,
        'direction' => AlertDirection::ABOVE,
    ]);

    // Simulate that the alert was removed from Redis when claimed
    Redis::del(['price_alerts:above', 'price_alerts:below']);
    expect(Redis::zscore('price_alerts:above', (string) $alert->id))->toBeFalse();

    app(RecoverStalePriceAlerts::class)->execute();

    expect($alert->fresh()->status)->toBe(AlertStatus::ACTIVE);
    expect((int) Redis::zscore('price_alerts:above', (string) $alert->id))->toBe(3450);

    // Also verify FAILED recovery reindexes
    $alert2 = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
        'processing_at' => now()->subMinutes(10),
        'target_price' => 3600,
        'direction' => AlertDirection::BELOW,
    ]);

    NotificationDelivery::factory()->failed()->create([
        'alert_id' => $alert2->id,
        'idempotency_key' => "price-alert:{$alert2->id}",
    ]);

    Redis::del(['price_alerts:below']);
    expect(Redis::zscore('price_alerts:below', (string) $alert2->id))->toBeFalse();

    app(RecoverStalePriceAlerts::class)->execute();

    expect($alert2->fresh()->status)->toBe(AlertStatus::ACTIVE);
    expect((int) Redis::zscore('price_alerts:below', (string) $alert2->id))->toBe(3600);
});
