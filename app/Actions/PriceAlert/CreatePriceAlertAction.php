<?php

namespace App\Actions\PriceAlert;

use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\PriceAlert;
use Throwable;

class CreatePriceAlertAction
{

    public function __construct(private PriceAlertIndex $priceAlertIndex) {}

    public function handle(int $userId, int $targetPrice, string $direction): PriceAlert
    {
        $alert = PriceAlert::create([
            'user_id' => $userId,
            'target_price' => $targetPrice,
            'direction' => AlertDirection::from($direction),
            'status' => AlertStatus::ACTIVE,
        ]);

        try {
            $this->priceAlertIndex->add(
                alertId: $alert->id,
                targetPrice: $targetPrice,
                direction: AlertDirection::from($direction),
            );
        } catch (Throwable $e) {
            report($e);
        }

        return $alert;
    }
}
