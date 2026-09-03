<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Enums\AlertDirection;

class PriceCrossingDetector
{
    public function crossed(int $previousPrice, int $currentPrice, int $targetPrice, AlertDirection $direction): bool
    {
        return match ($direction) {
            AlertDirection::ABOVE =>
            $previousPrice < $targetPrice
                && $currentPrice >= $targetPrice,

            AlertDirection::BELOW =>
            $previousPrice > $targetPrice
                && $currentPrice <= $targetPrice,
        };
    }
}
