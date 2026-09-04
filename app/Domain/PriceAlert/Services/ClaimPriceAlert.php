<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Models\OutboxMessage;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\DB;

class ClaimPriceAlert
{
    public function execute(int $alertId): ?PriceAlert
    {
        return DB::transaction(function () use ($alertId): ?PriceAlert {
            $updated = PriceAlert::query()
                ->whereKey($alertId)
                ->where('status', AlertStatus::ACTIVE)
                ->update([
                    'status' => AlertStatus::PROCESSING,
                    'processing_at' => now(),
                ]);

            if ($updated !== 1) {
                return null;
            }

            $alert = PriceAlert::findOrFail($alertId);

            OutboxMessage::create([
                'type' => 'price_alert.notification_requested',
                'aggregate_type' => PriceAlert::class,
                'aggregate_id' => $alert->id,
                'payload' => [
                    'alert_id' => $alert->id,
                    'user_id' => $alert->user_id,
                    'target_price' => $alert->target_price,
                    'direction' => $alert->direction->value,
                ],
            ]);

            return $alert;
        });
    }
}
