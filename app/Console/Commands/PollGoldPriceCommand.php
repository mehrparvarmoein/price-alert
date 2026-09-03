<?php

namespace App\Console\Commands;

use App\Domain\PriceAlert\Contracts\GoldPriceProvider;
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

    public function handle(GoldPriceProvider $provider, GoldPriceState $priceState): int
    {
        $price = $provider->getCurrentPrice();

        $previous = $priceState->update($price);

        $this->info(sprintf(
            'Price updated: %s (previous: %s)',
            $price,
            $previous ?? 'N/A',
        ));

        return self::SUCCESS;
    }
}
