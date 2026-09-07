<?php

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Services\ProcessPriceCrossing;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\PriceAlert;

beforeEach(function () {
    $this->priceAlertIndex = app(PriceAlertIndex::class);
});

it('claims only alerts crossed by the price movement', function () {

    $matching = PriceAlert::factory()->active()->above()->create([
        'target_price' => 4500,
    ]);

    $nonMatching = PriceAlert::factory()->active()->above()->create([
        'target_price' => 4600,
    ]);

    $this->priceAlertIndex->add(
        $matching->id,
        $matching->target_price,
        $matching->direction,
    );

    $this->priceAlertIndex->add(
        $nonMatching->id,
        $nonMatching->target_price,
        $nonMatching->direction,
    );

    $claimed = app(ProcessPriceCrossing::class)->execute(
        previousPrice: 4490,
        currentPrice: 4505,
    );

    expect($claimed)->toBe(1);

    expect($matching->fresh()->status)->toBe(AlertStatus::PROCESSING);

    expect($nonMatching->fresh()->status)->toBe(AlertStatus::ACTIVE);
});

it('matches all alerts crossed by a large upward movement', function () {

    foreach ([3100, 3200, 3500, 3900, 4500] as $price) {
        $alert = PriceAlert::factory()->active()->above()->create([
            'target_price' => $price,
        ]);

        $this->priceAlertIndex->add(
            $alert->id,
            $alert->target_price,
            $alert->direction,
        );
    }

    $claimed = app(ProcessPriceCrossing::class)->execute(
        previousPrice: 3000,
        currentPrice: 4000,
    );

    expect($claimed)->toBe(4);

    expect(
        PriceAlert::query()
            ->where('status', AlertStatus::PROCESSING)
            ->count()
    )->toBe(4);
    expect(
        PriceAlert::query()
            ->where('status', AlertStatus::ACTIVE)
            ->count()
    )->toBe(1);
});