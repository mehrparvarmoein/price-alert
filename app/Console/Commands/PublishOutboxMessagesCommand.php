<?php

namespace App\Console\Commands;

use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\OutboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('outbox:publish')]
#[Description('Publish pending outbox messages to the queue')]
class PublishOutboxMessagesCommand extends Command
{

    private const BATCH_SIZE = 100;

    public function handle(): int
    {
        DB::transaction(function (): void {
            OutboxMessage::query()
                ->unprocessed()
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get()
                ->each(function (OutboxMessage $message): void {
                    $this->publish($message);

                    $message->update([
                        'processed_at' => now(),
                    ]);
                });
        });

        return self::SUCCESS;
    }

    private function publish(OutboxMessage $message): void
    {
        match ($message->type) {
            'price_alert.notification_requested'
                => SendPriceAlertNotificationJob::dispatch(
                    $message->aggregate_id,
                ),

            default => logger()->warning('Unknown outbox message type skipped.', [
                'outbox_id' => $message->id,
                'type' => $message->type,
            ]),
        };
    }
}
