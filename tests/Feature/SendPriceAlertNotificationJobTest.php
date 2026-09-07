<?php

use App\Domain\PriceAlert\Contracts\AlertNotificationSender;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::del(['price_alerts:above', 'price_alerts:below']);
});

it('sends notification for processing alert and marks delivery sent and alert triggered', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $sender = app(AlertNotificationSender::class);
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

it('idempotent across double handle calls (only one delivery, second does not send)', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $sender1 = Mockery::mock(AlertNotificationSender::class);
    $sender1->shouldReceive('send')->once();

    $sender2 = Mockery::mock(AlertNotificationSender::class);
    $sender2->shouldReceive('send')->never();

    $job = new SendPriceAlertNotificationJob($alert->id);
    $job->handle($sender1);
    $job->handle($sender1);

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
});


it('sets failed_at to null when marking SENT', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $failed = NotificationDelivery::factory()->failed()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
    ]);


    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->once();

    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    $fresh = $failed->fresh();
    expect($fresh->status)->toBe(NotificationDeliveryStatus::SENT)
        ->and($fresh->failed_at)->toBeNull()
        ->and($fresh->sent_at)->not->toBeNull();
});

it('marks pending delivery as failed with failed_at when job permanently fails', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $delivery = NotificationDelivery::factory()->pending()->create([
        'alert_id' => $alert->id,
        'idempotency_key' => "price-alert:{$alert->id}",
    ]);

    Log::spy();

    $exception = new RuntimeException('sender exploded');
    (new SendPriceAlertNotificationJob($alert->id))->failed($exception);

    $fresh = $delivery->fresh();
    expect($fresh->status)->toBe(NotificationDeliveryStatus::FAILED)
        ->and($fresh->failed_at)->not->toBeNull()
        ->and($fresh->sent_at)->toBeNull();

    Log::shouldHaveReceived('error')
        ->once()
        ->with(
            'Price alert notification permanently failed.',
            Mockery::on(fn (array $context) => ($context['alert_id'] ?? null) === $alert->id
                && ($context['exception'] ?? null) === $exception)
        );
});

it('concurrent double-job sends only once', function () {
    $alert = PriceAlert::factory()->processing()->create();

    $sender = Mockery::mock(AlertNotificationSender::class);
    $sender->shouldReceive('send')->once()->with(Mockery::on(fn (PriceAlert $a) => $a->id === $alert->id));

    // First worker wins the PENDING -> SENDING claim and sends
    (new SendPriceAlertNotificationJob($alert->id))->handle($sender);

    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(1);

    // Second concurrent job (duplicate dispatch) must not send again
    $sender2 = Mockery::mock(AlertNotificationSender::class);
    $sender2->shouldReceive('send')->never();

    // Alert is now TRIGGERED, so second job early-returns on status check
    (new SendPriceAlertNotificationJob($alert->id))->handle($sender2);
    expect($alert->fresh()->status)->toBe(AlertStatus::TRIGGERED);
    expect(NotificationDelivery::where('alert_id', $alert->id)->count())->toBe(1);
});

