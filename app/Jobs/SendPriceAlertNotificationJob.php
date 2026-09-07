<?php

namespace App\Jobs;

use App\Domain\PriceAlert\Contracts\AlertNotificationSender;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendPriceAlertNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public int $timeout = 30;

    public function __construct(private int $alertId)
    {
        $this->onQueue('Notification');
    }

    public function handle(AlertNotificationSender $sender): void
    {
        $alert = PriceAlert::with('user')->find($this->alertId);

        if ($alert === null) {
            return;
        }

        if ($alert->status !== AlertStatus::PROCESSING) {
            return;
        }

        $delivery = NotificationDelivery::query()->createOrFirst(
            [
                'idempotency_key' => "price-alert:{$alert->id}",
            ],
            [
                'alert_id' => $alert->id,
                'status' => NotificationDeliveryStatus::PENDING,
            ],
        );

        if ($delivery->status === NotificationDeliveryStatus::SENT) {
            return;
        }

        // Atomic claim: only one worker may transition PENDING/FAILED -> SENDING
        $claimed = NotificationDelivery::query()
            ->where('id', $delivery->id)
            ->whereIn('status', [
                NotificationDeliveryStatus::PENDING,
                NotificationDeliveryStatus::FAILED,
            ])
            ->update([
                'status' => NotificationDeliveryStatus::SENDING,
                'sending_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        try {
            $sender->send($alert);
        } catch (Throwable $e) {
            NotificationDelivery::query()
                ->where('id', $delivery->id)
                ->where('status', NotificationDeliveryStatus::SENDING)
                ->update([
                    'status' => NotificationDeliveryStatus::PENDING,
                    'sending_at' => null,
                ]);

            throw $e;
        }

        DB::transaction(function () use ($alert, $delivery): void {
            NotificationDelivery::query()
                ->where('id', $delivery->id)
                ->where('status', NotificationDeliveryStatus::SENDING)
                ->update([
                    'status' => NotificationDeliveryStatus::SENT,
                    'sending_at' => null,
                    'sent_at' => now(),
                    'failed_at' => null,
                ]);

            PriceAlert::query()
                ->whereKey($alert->id)
                ->where('status', AlertStatus::PROCESSING)
                ->update([
                    'status' => AlertStatus::TRIGGERED,
                    'triggered_at' => now(),
                    'processing_at' => null,
                ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()
            ->where('idempotency_key', "price-alert:{$this->alertId}")
            ->whereIn('status', [
                NotificationDeliveryStatus::PENDING,
                NotificationDeliveryStatus::SENDING,
                NotificationDeliveryStatus::FAILED,
            ])
            ->update([
                'status' => NotificationDeliveryStatus::FAILED,
                'sending_at' => null,
                'failed_at' => now(),
            ]);

        logger()->error(
            'Price alert notification permanently failed.',
            [
                'alert_id' => $this->alertId,
                'exception' => $exception,
            ],
        );
    }
}
