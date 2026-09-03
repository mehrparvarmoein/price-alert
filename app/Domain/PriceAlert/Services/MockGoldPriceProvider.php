<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Contracts\GoldPriceProvider;

class MockGoldPriceProvider implements GoldPriceProvider
{
    public function getCurrentPrice(): int
    {
        return config('gold.mock_price');
    }
}