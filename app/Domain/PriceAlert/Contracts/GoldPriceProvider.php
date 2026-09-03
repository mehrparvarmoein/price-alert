<?php

namespace App\Domain\PriceAlert\Contracts;

interface GoldPriceProvider
{
    public function getCurrentPrice(): int;
}