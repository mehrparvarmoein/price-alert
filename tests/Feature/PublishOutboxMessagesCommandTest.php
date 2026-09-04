<?php

use App\Console\Commands\PublishOutboxMessagesCommand;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Queue;

function jobHasAlertId(int $expectedId): Closure
{
    return function (SendPriceAlertNotificationJob $job) use ($expectedId): bool {
        $ref = new ReflectionClass($job);
        $prop = $ref->getProperty('alertId');
        $prop->setAccessible(true);

        return $prop->getValue($job) === $expectedId;
    };
}

it('publishes pending outbox messages and marks them processed', function () {
    Queue::fake();

    $messages = OutboxMessage::factory()->count(3)->create();

    $this->artisan(PublishOutboxMessagesCommand::class)->assertSuccessful();

    Queue::assertPushed(SendPriceAlertNotificationJob::class, 3);

    foreach ($messages as $message) {
        expect($message->fresh()->processed_at)->not->toBeNull();
    }
});

it('passes the correct aggregate_id to the job', function () {
    Queue::fake();

    OutboxMessage::factory()->create([
        'aggregate_id' => 42,
        'payload' => ['alert_id' => 42],
    ]);

    $this->artisan(PublishOutboxMessagesCommand::class)->assertSuccessful();

    Queue::assertPushed(SendPriceAlertNotificationJob::class, jobHasAlertId(42));
});

it('does not publish already processed messages', function () {
    Queue::fake();

    OutboxMessage::factory()->processed()->create([
        'aggregate_id' => 1,
        'payload' => [],
    ]);

    $pending = OutboxMessage::factory()->pending()->create([
        'aggregate_id' => 2,
        'payload' => [],
    ]);

    $this->artisan(PublishOutboxMessagesCommand::class)->assertSuccessful();

    Queue::assertPushed(SendPriceAlertNotificationJob::class, 1);
    Queue::assertPushed(SendPriceAlertNotificationJob::class, jobHasAlertId($pending->aggregate_id));
});
