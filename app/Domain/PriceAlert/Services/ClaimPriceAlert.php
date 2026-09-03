<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Models\PriceAlert;

class ClaimPriceAlert
{
    public function execute(int $alertId): ?PriceAlert
    {
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

        return PriceAlert::find($alertId);
    }
}
