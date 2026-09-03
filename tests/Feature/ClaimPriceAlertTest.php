<?php

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Services\ClaimPriceAlert;
use App\Models\PriceAlert;
use App\Models\User;

it('claims an active alert only once(atomic update)', function () {
    $alert = PriceAlert::factory()->active()->create();

    $claim = app(ClaimPriceAlert::class);

    $first = $claim->execute($alert->id);
    $second = $claim->execute($alert->id);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull();

    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);
});