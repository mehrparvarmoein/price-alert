<?php

namespace App\Domain\PriceAlert\Services\Redis;


use App\Domain\PriceAlert\Enums\AlertDirection;
use Illuminate\Support\Facades\Redis;

class PriceAlertIndex
{
    const ABOVE_KEY = 'price_alerts:above';

    const BELOW_KEY = 'price_alerts:below';

    public function add(int $alertId, int $targetPrice, AlertDirection $direction): void
    {
        Redis::zadd(
            $this->key($direction),
            $targetPrice,
            $alertId,
        );
    }

    public function remove(int $alertId, AlertDirection $direction): void
    {
        Redis::zrem(
            $this->key($direction),
            (string) $alertId,
        );
    }

    private function key(AlertDirection $direction): string
    {
        return match ($direction) {
            AlertDirection::ABOVE => self::ABOVE_KEY,
            AlertDirection::BELOW => self::BELOW_KEY,
        };
    }

    /**
     * @return list<int>
     */
    public function aboveCandidates(int $previous, int $current): array
    {
        if ($current <= $previous) {
            return [];
        }

        return array_map(
            'intval',
            Redis::zrangebyscore(
                self::ABOVE_KEY,
                "($previous",
                $current,
            )
        );
    }

    /**
     * @return list<int>
     */
    public function belowCandidates(int $previous, int $current): array
    {
        if ($current >= $previous) {
            return [];
        }

        return array_map(
            'intval',
            Redis::zrangebyscore(
                self::BELOW_KEY,
                $current,
                "($previous",
            )
        );
    }
}
