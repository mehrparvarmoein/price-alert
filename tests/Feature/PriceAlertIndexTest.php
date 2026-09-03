<?php

use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::del('price_alerts:above');
    Redis::del('price_alerts:below');

    $this->priceAlertIndex = new PriceAlertIndex();
});


it('adds above alerts to the sorted set', function () {
    $this->priceAlertIndex->add(
        alertId: 1,
        targetPrice: 3500,
        direction: AlertDirection::ABOVE,
    );

    $result = Redis::zrange(
        'price_alerts:above',
        0,
        -1,
        true,
    );

    expect($result)->toHaveKey('1')
        ->and((int) $result['1'])->toBe(3500);
});

it('returns above candidates inside the crossed price range', function () {
    $this->priceAlertIndex->add(1, 3498, AlertDirection::ABOVE);
    $this->priceAlertIndex->add(2, 3500, AlertDirection::ABOVE);
    $this->priceAlertIndex->add(3, 3502, AlertDirection::ABOVE);

    $result = $this->priceAlertIndex->aboveCandidates(
        previous: 3499,
        current: 3501,
    );

    //only alertId 2 is in condition
    expect($result)->toBe([2]);
});

it('returns below candidates inside the crossed price range', function () {
    $this->priceAlertIndex->add(1, 3498, AlertDirection::BELOW);
    $this->priceAlertIndex->add(2, 3500, AlertDirection::BELOW);
    $this->priceAlertIndex->add(3, 3502, AlertDirection::BELOW);

    $result = $this->priceAlertIndex->belowCandidates(
        previous: 3501,
        current: 3499,
    );

    expect($result)->toBe([2]);
});

it('removes an alert from the index', function () {
    $this->priceAlertIndex->add(
        alertId: 1,
        targetPrice: 3500,
        direction: AlertDirection::ABOVE,
    );

    $this->priceAlertIndex->remove(
        alertId: 1,
        direction: AlertDirection::ABOVE,
    );

    expect(
        Redis::zscore('price_alerts:above', '1')
    )->toBeFalse();
});