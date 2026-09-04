<?php

use App\Domain\PriceAlert\Contracts\AlertNotificationSender;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;

it('creates only one notification delivery for an alert', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $job = new SendPriceAlertNotificationJob($alert->id);

    $sender = Mockery::mock(AlertNotificationSender::class);

    $sender->shouldReceive('send')->once();

    $job->handle($sender);

    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(1);

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
});

it('does not send an already delivered notification again', function () {
    $alert = PriceAlert::factory()->create();

    NotificationDelivery::factory()->sent()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
    ]);

    $sender = Mockery::mock(AlertNotificationSender::class);
    
    $job = new SendPriceAlertNotificationJob($alert->id);
    
    $job->handle($sender);

    $sender->shouldReceive('send')->never();

});