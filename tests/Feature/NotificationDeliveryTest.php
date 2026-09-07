<?php

use App\Domain\PriceAlert\Contracts\AlertNotificationSender;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;

it('creates only one notification delivery for an alert', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $job = new SendPriceAlertNotificationJob($alert->id);

    $sender = app(AlertNotificationSender::class);

    $job->handle($sender);
    $job->handle($sender);

    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(1);

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
});

it('does not send an already delivered notification again', function () {
    $alert = PriceAlert::factory()->processing()->create();

    NotificationDelivery::factory()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
        'status' => NotificationDeliveryStatus::SENT,
        'sent_at' => now(),
    ]);

    $sender = Mockery::mock(AlertNotificationSender::class);

    $job = new SendPriceAlertNotificationJob($alert->id);

    $job->handle($sender);

    $sender->shouldReceive('send')->never();
});

it('keeps the alert processing when notification fails', function () {

    $alert = PriceAlert::factory()->create([
        'status' => AlertStatus::PROCESSING,
    ]);

    $sender = Mockery::mock(AlertNotificationSender::class);

    $sender->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Provider unavailable'));


    $job = new SendPriceAlertNotificationJob($alert->id);

    expect(fn() => $job->handle($sender))
        ->toThrow(RuntimeException::class);

    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);

    expect(
        NotificationDelivery::query()
            ->where('alert_id', $alert->id)
            ->where('status', NotificationDeliveryStatus::PENDING)
            ->exists()
    )->toBeTrue();
});

it('updates notification delivery status to FAILED when job fails', function () {
    $alert = PriceAlert::factory()->create();
    
    $delivery = NotificationDelivery::create([
        'idempotency_key' => "price-alert:{$alert->id}",
        'alert_id' => $alert->id,
        'status' => NotificationDeliveryStatus::PENDING,
    ]);

    $exception = new \Exception('External Service Timeout');

    $job = new SendPriceAlertNotificationJob($alert->id);
    $job->failed($exception);

    $delivery->refresh();
    
    expect($delivery->status)->toBe(NotificationDeliveryStatus::FAILED)
        ->and($delivery->failed_at)->not->toBeNull()
        ->and($delivery->sending_at)->toBeNull();
});
