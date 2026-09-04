<?php

use App\Domain\PriceAlert\Contracts\AlertNotificationSender;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;

it('sends notification for processing alert and marks delivery sent and alert triggered', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->once()->with(Mockery::on(fn (PriceAlert $a) => $a->id === $alert->id));

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    $delivery = NotificationDelivery::where('alert_id', $alert->id)->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->idempotency_key)->toBe("price-alert:{$alert->id}")
        ->and($delivery->status)->toBe(NotificationDeliveryStatus::SENT)
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->failed_at)->toBeNull();

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED)
        ->and($alert->fresh()->triggered_at)->not->toBeNull();
});

it('creates delivery with PENDING then updates to SENT in same handle', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->once();

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(1);
    expect(NotificationDelivery::firstWhere('alert_id', $alert->id)->status)->toBe(NotificationDeliveryStatus::SENT);
});

it('does nothing when alert is not processing (active)', function () {
    $alert = PriceAlert::factory()->active()->create();

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->never();

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(0);
    expect($alert->fresh()->status)->toBe(AlertStatus::ACTIVE);
});

it('does nothing when alert is already triggered', function () {
    $alert = PriceAlert::factory()->triggered()->create();

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->never();

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(0);
    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
});

it('does not send when delivery is already SENT (idempotency)', function () {
    $alert = PriceAlert::factory()->processing()->create();

    NotificationDelivery::factory()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
        'status' => NotificationDeliveryStatus::SENT,
        'sent_at' => now(),
        'failed_at' => null,
    ]);

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->never();

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    // Alert should stay PROCESSING, not become TRIGGERED again
    expect($alert->fresh()->status)->toBe(AlertStatus::PROCESSING);
    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(1);
    expect(NotificationDelivery::firstWhere('alert_id', $alert->id)->status)->toBe(NotificationDeliveryStatus::SENT);
});

it('reuses existing PENDING delivery and marks it SENT', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $pending = NotificationDelivery::factory()->pending()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
    ]);

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->once();

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(1);

    $fresh = $pending->fresh();
    expect($fresh->id)->toBe($pending->id)
        ->and($fresh->status)->toBe(NotificationDeliveryStatus::SENT)
        ->and($fresh->sent_at)->not->toBeNull()
        ->and($fresh->failed_at)->toBeNull();

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
});

it('it is idempotent across double handle calls (only one delivery, second does not send)', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $sender1 = Mockery::mock(AlertNotificationSender::class);
    $sender1->shouldReceive('send')->once();

    $sender2 = Mockery::mock(AlertNotificationSender::class);
    $sender2->shouldReceive('send')->never();

    $job1 = new SendPriceAlertNotificationJob($alert->id);
    $job1->handle($sender1);

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);

    // Reset alert to PROCESSING to simulate retry after delivery became SENT
    // Actually second job should see SENT and return even if alert is still PROCESSING.
    // So create a fresh processing alert with already SENT delivery
    $alert2 = PriceAlert::factory()->processing()->create();
    NotificationDelivery::factory()->sent()->create([
        'alert_id' => $alert2->id,
        'idempotency_key' => "price-alert:{$alert2->id}",
    ]);

    $job2 = new SendPriceAlertNotificationJob($alert2->id);
    $job2->handle($sender2);

    expect(NotificationDelivery::where('alert_id', $alert2->id)->count())->toBe(1);
});


it('sets failed_at to null when marking SENT', function () {
    $alert = PriceAlert::factory()->processing()->create();

    // Create a FAILED delivery with same key – firstOrCreate will find it, not create new
    $failed = NotificationDelivery::factory()->failed()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
    ]);

    expect($failed->failed_at)->not->toBeNull();

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->once();

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    $fresh = $failed->fresh();
    expect($fresh->status)->toBe(NotificationDeliveryStatus::SENT)
        ->and($fresh->failed_at)->toBeNull()
        ->and($fresh->sent_at)->not->toBeNull();
});

