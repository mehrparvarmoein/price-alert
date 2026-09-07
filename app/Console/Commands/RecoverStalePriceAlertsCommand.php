<?php

namespace App\Console\Commands;

use App\Domain\PriceAlert\Services\RecoverStalePriceAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerts:recover-stale')]
#[Description('Recover price alerts stuck in processing state')]
class RecoverStalePriceAlertsCommand extends Command
{
    public function handle(RecoverStalePriceAlerts $recovery): int
    {
        $count = $recovery->execute();

        $this->info("Recovered {$count} alerts.");

        return self::SUCCESS;
    }
}
