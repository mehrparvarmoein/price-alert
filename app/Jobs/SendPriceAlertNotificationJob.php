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

class SendPriceAlertNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private int $alertId) {}

    public function handle(AlertNotificationSender $sender): void
    {
        $alert = PriceAlert::with('user')->find($this->alertId);

        if ($alert === null) {
            return;
        }

        if ($alert->status !== AlertStatus::PROCESSING) {
            return;
        }

        $delivery = DB::transaction(function () use ($alert): NotificationDelivery {
            return NotificationDelivery::firstOrCreate(
                [
                    'idempotency_key' => "price-alert:{$alert->id}",
                ],
                [
                    'alert_id' => $alert->id,
                    'status' => NotificationDeliveryStatus::PENDING,
                ],
            );
        });

        if ($delivery->status === NotificationDeliveryStatus::SENT) {
            return;
        }

        $sender->send($alert);

        DB::transaction(function () use ($alert, $delivery): void {
            $delivery->update([
                'status' => NotificationDeliveryStatus::SENT,
                'sent_at' => now(),
                'failed_at' => null,
            ]);

            $alert->update([
                'status' => AlertStatus::TRIGGERED,
                'triggered_at' => now(),
                'processing_at' => null,
            ]);
        });
    }
}
