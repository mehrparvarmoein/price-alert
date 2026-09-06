<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecoverStalePriceAlerts
{
    public function __construct(private PriceAlertIndex $priceAlertIndex) {}

    public function execute(int $minutes = 5): int
    {
        $threshold = now()->subMinutes($minutes);
        $recovered = 0;

        PriceAlert::query()
            ->where('status', AlertStatus::PROCESSING)
            ->where('processing_at', '<', $threshold)
            ->orderBy('id')
            ->chunkById(500, function (Collection $alerts) use ($threshold, &$recovered): void {
                foreach ($alerts as $alert) {
                    $delivery = NotificationDelivery::query()
                        ->where('alert_id', $alert->id)
                        ->first();

                    if ($delivery?->status === NotificationDeliveryStatus::SENT) {
                        $alert->update([
                            'status' => AlertStatus::TRIGGERED,
                            'triggered_at' => $delivery->sent_at ?? now(),
                            'processing_at' => null,
                        ]);

                        continue;
                    }

                    if ($delivery?->status === NotificationDeliveryStatus::FAILED) {

                        DB::transaction(function () use ($alert, $delivery, &$recovered): void {
                            $updated = PriceAlert::query()
                                ->whereKey($alert->id)
                                ->where('status', AlertStatus::PROCESSING)
                                ->update([
                                    'status' => AlertStatus::ACTIVE,
                                    'processing_at' => null,
                                ]);

                            if ($updated === 1) {
                                $delivery->delete();

                                try {
                                    $this->priceAlertIndex->add(
                                        alertId: $alert->id,
                                        targetPrice: $alert->target_price,
                                        direction: $alert->direction,
                                    );
                                } catch (Throwable $e) {
                                    report($e);
                                }

                                $recovered++;
                            }
                        });

                        continue;
                    }

                    $sendingLeaseExpired = $delivery?->status === NotificationDeliveryStatus::SENDING
                        && (
                            $delivery->sending_at?->lt($threshold)
                            ?? $alert->processing_at?->lt($threshold)
                        );

                    if ($sendingLeaseExpired) {
                        $reset = NotificationDelivery::query()
                            ->whereKey($delivery->id)
                            ->where('status', NotificationDeliveryStatus::SENDING)
                            ->where(function ($query) use ($threshold): void {
                                $query
                                    ->where('sending_at', '<', $threshold)
                                    ->orWhere(function ($query) use ($threshold): void {
                                        $query
                                            ->whereNull('sending_at')
                                            ->where('updated_at', '<', $threshold);
                                    });
                            })
                            ->update([
                                'status' => NotificationDeliveryStatus::PENDING,
                                'sending_at' => null,
                            ]);

                        if ($reset === 1) {
                            SendPriceAlertNotificationJob::dispatch($alert->id);
                        }

                        continue;
                    }

                    if ($delivery?->status === NotificationDeliveryStatus::PENDING) {
                        // Let queue retries handle transient PENDING.
                        continue;
                    }

                    // No delivery record.
                    $updated = PriceAlert::query()
                        ->whereKey($alert->id)
                        ->where('status', AlertStatus::PROCESSING)
                        ->update([
                            'status' => AlertStatus::ACTIVE,
                            'processing_at' => null,
                        ]);

                    if ($updated === 1) {
                        try {
                            $this->priceAlertIndex->add(
                                alertId: $alert->id,
                                targetPrice: $alert->target_price,
                                direction: $alert->direction,
                            );
                        } catch (Throwable $e) {
                            report($e);
                        }
                    }

                    $recovered += $updated;
                }
            });

        return $recovered;
    }
}
