<?php

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Services\ClaimPriceAlert;
use App\Models\PriceAlert;

it('claims an active alert only once(atomic update)', function () {
    $alert = PriceAlert::factory()->active()->create();

    $claim = app(ClaimPriceAlert::class);

    $first = $claim->execute($alert->id);
    $second = $claim->execute($alert->id);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull();

    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);
    
    $this->assertDatabaseHas('outbox_messages', [
        'type' => 'price_alert.notification_requested',
        'aggregate_type' => PriceAlert::class,
        'aggregate_id' => $alert->id,
        'payload->alert_id' => $alert->id,
        'payload->user_id' => $alert->user_id,
        'payload->target_price' => $alert->target_price,
        'payload->direction' => $alert->direction->value,
    ]);
});