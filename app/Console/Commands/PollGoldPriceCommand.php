<?php

namespace App\Console\Commands;

use App\Domain\PriceAlert\Contracts\GoldPriceProvider;
use App\Domain\PriceAlert\Services\ProcessPriceCrossing;
use App\Domain\PriceAlert\Services\Redis\GoldPriceState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gold:poll')]
#[Description('Poll the current gold price')]
class PollGoldPriceCommand extends Command
{
    protected $signature = 'gold:poll';

    protected $description = 'Poll the current gold price';

    public function handle(GoldPriceProvider $provider, GoldPriceState $priceState, ProcessPriceCrossing $processCrossing): int
    {
        $price = $provider->getCurrentPrice();

        $previous = $priceState->update($price);

        if ($previous === null || $previous === $price) {
            return self::SUCCESS;
        }

        $claimed = $processCrossing->execute(
            previousPrice: $previous,
            currentPrice: $price,
        );

        $this->info(sprintf(
            'Price: %s, Previous: %s, Claimed alerts: %d',
            $price,
            $previous,
            $claimed,
        ));

        return self::SUCCESS;
    }
}
