<?php

use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Domain\PriceAlert\Services\ProcessPriceCrossing;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\OutboxMessage;
use App\Models\PriceAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function () {
    Redis::del([
        'price_alerts:above',
        'price_alerts:below',
        'gold:current_price',
        'gold:previous_price',
    ]);
});

it('processes a price crossing and sends one notification', function () {
    $alert = PriceAlert::factory()->active()->above()->create([
        'target_price' => 3500,
    ]);

    app(PriceAlertIndex::class)->add(
        alertId: $alert->id,
        targetPrice: $alert->target_price,
        direction: $alert->direction,
    );

    $claimed = app(ProcessPriceCrossing::class)->execute(
        previousPrice: 3490,
        currentPrice: 3505,
    );

    expect($claimed)->toBe(1);

    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);

    expect(
        OutboxMessage::query()
            ->where('aggregate_id', $alert->id)
            ->count()
    )->toBe(1);

    $job = new SendPriceAlertNotificationJob($alert->id);

    $job->handle(app(\App\Domain\PriceAlert\Contracts\AlertNotificationSender::class));

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);

    expect(
        NotificationDelivery::query()
            ->where('alert_id', $alert->id)
            ->where('status', NotificationDeliveryStatus::SENT)
            ->count()
    )->toBe(1);
});

it('does not process the same alert twice', function () {

    $alert = PriceAlert::factory()->active()->above()->create([
        'target_price' => 3500,
    ]);

    app(PriceAlertIndex::class)->add(
        $alert->id,
        $alert->target_price,
        $alert->direction,
    );

    $processor = app(ProcessPriceCrossing::class);

    $first = $processor->execute(
        previousPrice: 3490,
        currentPrice: 3505,
    );

    $second = $processor->execute(
        previousPrice: 3490,
        currentPrice: 3505,
    );

    expect($first)->toBe(1)->and($second)->toBe(0);

    expect(
        OutboxMessage::query()
            ->where('aggregate_id', $alert->id)
            ->count()
    )->toBe(1);
});
