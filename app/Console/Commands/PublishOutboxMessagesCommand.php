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
    protected $signature = 'outbox:publish';

    protected $description = 'Publish pending outbox messages to the queue';

    private const BATCH_SIZE = 100;

    public function handle(): int
    {
        DB::transaction(function (): void {
            OutboxMessage::query()
                ->whereNull('processed_at')
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

            default => null,
        };
    }
}
