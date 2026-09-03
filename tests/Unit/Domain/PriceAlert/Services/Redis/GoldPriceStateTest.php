<?php

use App\Domain\PriceAlert\Services\Redis\GoldPriceState;
use Illuminate\Support\Facades\Redis;

uses(Tests\TestCase::class);

describe(GoldPriceState::class, function () {
    beforeEach(function () {
        $this->state = new GoldPriceState();
    });

    describe('current(): ?int', function () {
        it('returns null when no current price is stored', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY)
                ->andReturn(null);

            expect($this->state->current())->toBeNull();
        });

        it('returns the value stored in redis for current price', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY)
                ->andReturn('3500');

            $result = $this->state->current();

            expect($result)->toBe(3500);
            expect($result)->toBeInt();
        });
    });

    describe('previous(): ?int', function () {
        it('returns null when no previous price is stored', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::PREVIOUS_KEY)
                ->andReturn(null);

            expect($this->state->previous())->toBeNull();
        });

        it('returns the value stored in redis for previous price', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::PREVIOUS_KEY)
                ->andReturn('3400');

            expect($this->state->previous())->toBe(3400);
        });
    });

    describe('update(int $price): ?int', function () {
        it('stores the price as current when no current exists and returns null', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY)
                ->andReturn(null);

            Redis::shouldReceive('set')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY, 3500)
                ->andReturn(true);

            // must NOT touch previous key on first write
            Redis::shouldReceive('set')
                ->with(GoldPriceState::PREVIOUS_KEY, \Mockery::any())
                ->never();

            expect($this->state->update(3500))->toBeNull();
        });

        it('moves current to previous and stores new current, returning old current', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY)
                ->andReturn('3500');

            Redis::shouldReceive('set')
                ->once()
                ->with(GoldPriceState::PREVIOUS_KEY, 3500)
                ->andReturn(true);

            Redis::shouldReceive('set')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY, 3600)
                ->andReturn(true);

            $result = $this->state->update(3600);

            expect($result)->toBe(3500);
        });

        it('handles updating with the same price', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY)
                ->andReturn('3500');
            Redis::shouldReceive('set')
                ->once()
                ->with(GoldPriceState::PREVIOUS_KEY, 3500)
                ->andReturn(true);
            Redis::shouldReceive('set')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY, 3500)
                ->andReturn(true);

            expect($this->state->update(3500))->toBe(3500);
        });

        it('delegates to current() internally', function () {
            Redis::shouldReceive('get')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY)
                ->andReturn(null);
            Redis::shouldReceive('set')
                ->once()
                ->with(GoldPriceState::CURRENT_KEY, 9999)
                ->andReturn(true);

            $this->state->update(9999);
        });
    });
});
