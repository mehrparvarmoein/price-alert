<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\PriceAlert;

class ProcessPriceCrossing
{
    public function __construct(
        private PriceAlertIndex $priceAlertIndex,
        private PriceCrossingDetector $detector,
        private ClaimPriceAlert $claim,
    ) {}

    public function execute(int $previousPrice, int $currentPrice): int 
    {
        if ($previousPrice === $currentPrice) {
            return 0;
        }

        //find candidateIds from redis
        $candidateIds = $currentPrice > $previousPrice
            ? $this->priceAlertIndex->aboveCandidates(
                $previousPrice,
                $currentPrice,
            )
            : $this->priceAlertIndex->belowCandidates(
                $previousPrice,
                $currentPrice,
            );

        if ($candidateIds === []) {
            return 0;
        }

        //check candidateIds with database as source of truth
        $alerts = PriceAlert::query()
            ->whereIn('id', $candidateIds)
            ->where('status', AlertStatus::ACTIVE)
            ->get();

        $claimed = 0;

        foreach ($alerts as $alert) {

            if (! $this->detector->crossed(
                previousPrice: $previousPrice,
                currentPrice: $currentPrice,
                targetPrice: $alert->target_price,
                direction: $alert->direction,
            )) {
                continue;
            }

            //atomic update alert from active status to processing status and create outbox message
            if ($this->claim->execute($alert->id) === null) {
                continue;
            }

            $this->priceAlertIndex->remove(
                alertId: $alert->id,
                direction: $alert->direction,
            );

            $claimed++;
        }

        return $claimed;
    }
}
