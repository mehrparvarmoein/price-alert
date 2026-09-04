<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\PriceAlert;

class RecoverStalePriceAlerts
{
    public function execute(int $minutes = 5): int
    {
        $alerts = PriceAlert::query()
            ->where('status', AlertStatus::PROCESSING)
            ->where('processing_at', '<', now()->subMinutes($minutes))
            ->get();

        $recovered = 0;

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

            if ($delivery !== null) {
                continue;
            }

            $updated = PriceAlert::query()
                ->whereKey($alert->id)
                ->where('status', AlertStatus::PROCESSING)
                ->update([
                    'status' => AlertStatus::ACTIVE,
                    'processing_at' => null,
                ]);

            $recovered += $updated;
        }

        return $recovered;
    }
}
