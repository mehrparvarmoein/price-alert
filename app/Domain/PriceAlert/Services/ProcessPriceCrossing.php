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

        //check candidateIds from database as source of truth
        $alerts = PriceAlert::query()
            ->whereIn('id', $candidateIds)
            ->where('status', AlertStatus::ACTIVE)
            ->get()
            ->keyBy('id');

        $claimed = 0;

        foreach ($candidateIds as $alertId) {
            $alert = $alerts->get($alertId);

            if ($alert === null) {
                continue;
            }

            if (! $this->detector->crossed(
                previousPrice: $previousPrice,
                currentPrice: $currentPrice,
                targetPrice: $alert->target_price,
                direction: $alert->direction,
            )) {
                continue;
            }

            //atomic update alert from active status to processing status
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

    // public function execute(int $previousPrice, int $currentPrice): int
    // {
    //     $candidateIds = $this->matcher->execute(
    //         previousPrice: $previousPrice,
    //         currentPrice: $currentPrice,
    //     );

    //     $claimed = 0;

    //     foreach ($candidateIds as $alertId) {
    //         $alert = $this->claim->execute($alertId);

    //         if ($alert === null) {
    //             continue;
    //         }

    //         $claimed++;
    //     }

    //     return $claimed;
    // }
}
