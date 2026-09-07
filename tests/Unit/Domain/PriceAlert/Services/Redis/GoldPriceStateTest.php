<?php

use App\Domain\PriceAlert\Services\Redis\GoldPriceState;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->state = new GoldPriceState;
});

describe('current', function () {
    it('returns null when no current price is stored', function () {
        Redis::shouldReceive('get')
            ->once()
            ->with(GoldPriceState::CURRENT_KEY)
            ->andReturn(null);

        expect($this->state->current())->toBeNull();
    });

    it('casts the stored string value to int', function (string $stored, int $expected) {
        Redis::shouldReceive('get')
            ->once()
            ->with(GoldPriceState::CURRENT_KEY)
            ->andReturn($stored);

        $result = $this->state->current();

        expect($result)->toBe($expected)->toBeInt();
    })->with([
        'typical price' => ['3500', 3500],
        'zero is a valid price' => ['0', 0],
    ]);
});

describe('previous', function () {
    it('returns null when no previous price is stored', function () {
        Redis::shouldReceive('get')
            ->once()
            ->with(GoldPriceState::PREVIOUS_KEY)
            ->andReturn(null);

        expect($this->state->previous())->toBeNull();
    });

    it('casts the stored string value to int', function (string $stored, int $expected) {
        Redis::shouldReceive('get')
            ->once()
            ->with(GoldPriceState::PREVIOUS_KEY)
            ->andReturn($stored);

        $result = $this->state->previous();

        expect($result)->toBe($expected)->toBeInt();
    })->with([
        'typical price' => ['3400', 3400],
        'zero is a valid price' => ['0', 0],
    ]);
});

describe('update', function () {
    it('stores the price as current without touching previous on first write', function () {
        Redis::shouldReceive('get')
            ->once()
            ->with(GoldPriceState::CURRENT_KEY)
            ->andReturn(null);

        Redis::shouldReceive('set')
            ->once()
            ->with(GoldPriceState::CURRENT_KEY, 3500)
            ->andReturnTrue();

        Redis::shouldReceive('set')
            ->with(GoldPriceState::PREVIOUS_KEY, \Mockery::any())
            ->never();

        expect($this->state->update(3500))->toBeNull();
    });

    it('rotates current to previous and returns the old price', function () {
        Redis::shouldReceive('get')
            ->once()
            ->with(GoldPriceState::CURRENT_KEY)
            ->andReturn('3500');

        Redis::shouldReceive('set')
            ->once()
            ->with(GoldPriceState::PREVIOUS_KEY, 3500)
            ->andReturnTrue();

        Redis::shouldReceive('set')
            ->once()
            ->with(GoldPriceState::CURRENT_KEY, 3600)
            ->andReturnTrue();

        $result = $this->state->update(3600);

        expect($result)->toBe(3500)->toBeInt();
    });
});
