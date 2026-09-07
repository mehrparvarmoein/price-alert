<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Jobs\SendPriceAlertNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecoverStalePriceAlerts
{
    private const CHUNK_SIZE = 500;

    public function __construct(private PriceAlertIndex $priceAlertIndex) {}

    /**
     * Reactivate alerts stuck in PROCESSING.
     * Returns the number of alerts reactivated to ACTIVE.
     */
    public function execute(int $minutes = 5): int
    {
        $threshold = now()->subMinutes($minutes);
        $recovered = 0;

        PriceAlert::query()
            ->with('notificationDelivery')
            ->where('status', AlertStatus::PROCESSING)
            ->where('processing_at', '<', $threshold)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $alerts) use ($threshold, &$recovered): void {
                foreach ($alerts as $alert) {
                    if ($this->handle($alert, $threshold)) {
                        $recovered++;
                    }
                }
            });

        return $recovered;
    }

    private function handle(PriceAlert $alert, CarbonInterface $threshold): bool
    {
        $delivery = $alert->notificationDelivery;

        return match ($delivery?->status) {
            NotificationDeliveryStatus::SENT => $this->finalizeSent($alert, $delivery),
            NotificationDeliveryStatus::FAILED => $this->recoverFailed($alert, $delivery),
            NotificationDeliveryStatus::SENDING => $this->retrySending($delivery, $threshold),
            NotificationDeliveryStatus::PENDING => false,
            null => $this->recoverOrphaned($alert),
        };
    }

    private function finalizeSent(PriceAlert $alert, NotificationDelivery $delivery): bool
    {
        PriceAlert::query()
            ->whereKey($alert->id)
            ->where('status', AlertStatus::PROCESSING)
            ->update([
                'status' => AlertStatus::TRIGGERED,
                'triggered_at' => $delivery->sent_at ?? now(),
                'processing_at' => null,
            ]);

        return false;
    }

    private function recoverFailed(PriceAlert $alert, NotificationDelivery $delivery): bool
    {
        $reactivated = DB::transaction(function () use ($alert, $delivery): bool {
            $updated = PriceAlert::query()
                ->whereKey($alert->id)
                ->where('status', AlertStatus::PROCESSING)
                ->update([
                    'status' => AlertStatus::ACTIVE,
                    'processing_at' => null,
                ]);

            if ($updated !== 1) {
                return false;
            }

            NotificationDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', NotificationDeliveryStatus::FAILED)
                ->delete();

            return true;
        });

        if ($reactivated) {
            $this->reindex($alert);
        }

        return $reactivated;
    }

    private function retrySending(NotificationDelivery $delivery, CarbonInterface $threshold): bool
    {
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
            SendPriceAlertNotificationJob::dispatch($delivery->alert_id);
        }

        return false;
    }

    private function recoverOrphaned(PriceAlert $alert): bool
    {
        $updated = PriceAlert::query()
            ->whereKey($alert->id)
            ->where('status', AlertStatus::PROCESSING)
            ->update([
                'status' => AlertStatus::ACTIVE,
                'processing_at' => null,
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->reindex($alert);

        return true;
    }

    private function reindex(PriceAlert $alert): void
    {
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
}
