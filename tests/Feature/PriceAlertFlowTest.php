<?php

use App\Console\Commands\PublishOutboxMessagesCommand;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Domain\PriceAlert\Services\ProcessPriceCrossing;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\NotificationDelivery;
use App\Models\OutboxMessage;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::del([
        'price_alerts:above',
        'price_alerts:below',
        'gold:current_price',
        'gold:previous_price',
    ]);

    $this->priceAlertIndex = app(PriceAlertIndex::class);
    $this->processor = app(ProcessPriceCrossing::class);
});

it('processes a price crossing and sends one notification', function () {
    $alert = PriceAlert::factory()->active()->above()->create([
        'target_price' => 3500,
    ]);

    $this->priceAlertIndex->add(
        alertId: $alert->id,
        targetPrice: $alert->target_price,
        direction: $alert->direction,
    );

    $claimed = $this->processor->execute(
        previousPrice: 3490,
        currentPrice: 3505,
    );

    expect($claimed)->toBe(1);

    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);

    expect(
        OutboxMessage::query()
            ->where('aggregate_id', $alert->id)
            ->whereNull('processed_at')
            ->count()
    )->toBe(1);

    Artisan::call(PublishOutboxMessagesCommand::class);

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);

    expect(
        NotificationDelivery::query()
            ->where('alert_id', $alert->id)
            ->where('status', NotificationDeliveryStatus::SENT)
            ->count()
    )->toBe(1);

    expect(
        OutboxMessage::query()
            ->where('aggregate_id', $alert->id)
            ->whereNotNull('processed_at')
            ->count()
    )->toBe(1);

    expect(
        Redis::zscore('price_alerts:above', '1')
    )->toBeFalse();
});

it('does not process the same alert twice', function () {

    $alert = PriceAlert::factory()->active()->above()->create([
        'target_price' => 3500,
    ]);

    $this->priceAlertIndex->add(
        $alert->id,
        $alert->target_price,
        $alert->direction,
    );

    $first = $this->processor->execute(
        previousPrice: 3490,
        currentPrice: 3505,
    );

    $second = $this->processor->execute(
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
