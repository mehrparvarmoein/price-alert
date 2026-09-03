<?php

declare(strict_types=1);

use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Services\PriceCrossingDetector;

beforeEach(function () {
    $this->detector = new PriceCrossingDetector();
});

it('detects an upward crossing', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3499,
            currentPrice: 3500,
            targetPrice: 3500,
            direction: AlertDirection::ABOVE,
        )
    )->toBeTrue();
});

it('detects an upward crossing when price jumps over target', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3499,
            currentPrice: 3501,
            targetPrice: 3500,
            direction: AlertDirection::ABOVE,
        )
    )->toBeTrue();
});

it('does not trigger above when price was already at target', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3500,
            currentPrice: 3501,
            targetPrice: 3500,
            direction: AlertDirection::ABOVE,
        )
    )->toBeFalse();
});

it('does not trigger above when price remains below target', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 349800,
            currentPrice: 349900,
            targetPrice: 350000,
            direction: AlertDirection::ABOVE,
        )
    )->toBeFalse();
});

it('detects a downward crossing', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3501,
            currentPrice: 3500,
            targetPrice: 3500,
            direction: AlertDirection::BELOW,
        )
    )->toBeTrue();
});

it('detects a downward crossing when price jumps below target', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3501,
            currentPrice: 3499,
            targetPrice: 3500,
            direction: AlertDirection::BELOW,
        )
    )->toBeTrue();
});

it('does not trigger below when price was already at target', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3500,
            currentPrice: 3499,
            targetPrice: 3500,
            direction: AlertDirection::BELOW,
        )
    )->toBeFalse();
});

it('does not trigger below when price remains above target', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3502,
            currentPrice: 3501,
            targetPrice: 3500,
            direction: AlertDirection::BELOW,
        )
    )->toBeFalse();
});

it('does not trigger when price does not change', function () {
    expect(
        $this->detector->crossed(
            previousPrice: 3500,
            currentPrice: 3500,
            targetPrice: 3500,
            direction: AlertDirection::ABOVE,
        )
    )->toBeFalse();
});