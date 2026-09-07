<?php

use App\Console\Commands\RebuildPriceAlertIndexCommand;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\Redis;


it('rebuilds redis indexes from active database alerts', function () {

    $above = PriceAlert::factory()->active()->above()->create([
        'target_price' => 3500,
    ]);

    $below = PriceAlert::factory()->active()->below()->create([
        'target_price' => 3400,
    ]);

    $triggered = PriceAlert::factory()->triggered()->above()->create([
        'target_price' => 3600,
    ]);

    Redis::del([
        'price_alerts:above',
        'price_alerts:below',
    ]);

    $this->artisan(RebuildPriceAlertIndexCommand::class)->assertSuccessful();

    expect(
        (int) Redis::zscore(
            'price_alerts:above',
            $above->id,
        )
    )->toBe(3500);

    expect(
        (int) Redis::zscore(
            'price_alerts:below',
            $below->id,
        )
    )->toBe(3400);

    
    expect(
        Redis::zscore(
            'price_alerts:above',
            $triggered->id,
        )
    )->toBeFalse();
});
