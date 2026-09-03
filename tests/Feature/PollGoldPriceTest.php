<?php

use App\Console\Commands\PollGoldPriceCommand;
use App\Domain\PriceAlert\Contracts\GoldPriceProvider;
use App\Domain\PriceAlert\Services\Redis\GoldPriceState;
use Illuminate\Console\Command;
use Mockery\MockInterface;

it('polls gold price and displays N/A when there is no previous price', function () {
    $provider = $this->mock(GoldPriceProvider::class, function (MockInterface $mock) {
        $mock->shouldReceive('getCurrentPrice')->once()->andReturn(3500);
    });

    $priceState = $this->mock(GoldPriceState::class, function (MockInterface $mock) {
        $mock->shouldReceive('update')->once()->with(3500)->andReturn(null);
    });

    $this->artisan(PollGoldPriceCommand::class)
        ->expectsOutput('Price updated: 3500 (previous: N/A)')
        ->assertExitCode(Command::SUCCESS);
});

it('polls gold price and displays previous price when it exists', function () {
    $this->mock(GoldPriceProvider::class, function (Mockery\MockInterface $mock) {
        $mock->shouldReceive('getCurrentPrice')->once()->andReturn(3600);
    });

    $this->mock(GoldPriceState::class, function (Mockery\MockInterface $mock) {
        $mock->shouldReceive('update')->once()->with(3600)->andReturn(3500);
    });

    $this->artisan(PollGoldPriceCommand::class)
        ->expectsOutput('Price updated: 3600 (previous: 3500)')
        ->assertExitCode(Command::SUCCESS);
});
