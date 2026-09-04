<?php

namespace App\Console\Commands;

use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\OutboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('outbox:publish')]
#[Description('Publish pending outbox messages to the queue')]
class PublishOutboxMessagesCommand extends Command
{
    protected $signature = 'outbox:publish';

    protected $description = 'Publish pending outbox messages to the queue';

    public function handle(): int
    {
        OutboxMessage::query()
            ->whereNull('processed_at')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (OutboxMessage $message): void {
                if ($message->type !== 'price_alert.notification_requested') {
                    return;
                }

                SendPriceAlertNotificationJob::dispatch(
                    $message->aggregate_id,
                );

                $message->update([
                    'processed_at' => now(),
                ]);
            });

        return self::SUCCESS;
    }
}
