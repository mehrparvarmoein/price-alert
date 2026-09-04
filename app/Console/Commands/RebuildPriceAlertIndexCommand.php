<?php

namespace App\Console\Commands;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\PriceAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

#[Signature('alerts:rebuild-index')]
#[Description('Rebuild Redis price alert indexes from PostgreSQL')]
class RebuildPriceAlertIndexCommand extends Command
{
    protected $signature = 'alerts:rebuild-index';

    protected $description = 'Rebuild Redis price alert indexes from PostgreSQL';

    public function handle(PriceAlertIndex $priceAlertIndex): int
    {
        PriceAlert::query()
            ->where('status', AlertStatus::ACTIVE)
            ->orderBy('id')
            ->chunkById(1000, function ($alerts) use ($priceAlertIndex): void {
                foreach ($alerts as $alert) {
                    $priceAlertIndex->add(
                        alertId: $alert->id,
                        targetPrice: $alert->target_price,
                        direction: $alert->direction,
                    );
                }
            });

        $this->info('Price alert indexes rebuilt.');

        return self::SUCCESS;
    }
}
