<?php

namespace App\Domain\PriceAlert\Services\Redis;

use Illuminate\Support\Facades\Redis;

class GoldPriceState
{
    const CURRENT_KEY = 'gold:current_price';

    const PREVIOUS_KEY = 'gold:previous_price';

    public function current(): ?int
    {
        $value = Redis::get(self::CURRENT_KEY);

        return $value === null ? null : (int) $value;
    }

    public function previous(): ?int
    {
        $value = Redis::get(self::PREVIOUS_KEY);

        return $value === null ? null : (int) $value;
    }

    public function update(int $price): ?int
    {
        $current = $this->current();

        if ($current === null) {
            Redis::set(self::CURRENT_KEY, $price);

            return null;
        }

        Redis::set(self::PREVIOUS_KEY, $current);
        Redis::set(self::CURRENT_KEY, $price);

        return $current;
    }
}
