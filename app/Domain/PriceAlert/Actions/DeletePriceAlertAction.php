<?php

namespace App\Domain\PriceAlert\Actions;

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Enums\DeletePriceAlertResult;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\PriceAlert;
use Throwable;

class DeletePriceAlertAction
{
    public function __construct(private PriceAlertIndex $priceAlertIndex) {}

    public function handle(int $userId, PriceAlert $alert): DeletePriceAlertResult
    {
        if ($alert->user_id !== $userId) {
            return DeletePriceAlertResult::NOT_FOUND;
        }

        if ($alert->status !== AlertStatus::ACTIVE) {
            return DeletePriceAlertResult::NOT_DELETABLE;
        }

        $deleted = PriceAlert::query()
            ->where('id', $alert->id)
            ->where('status', AlertStatus::ACTIVE)
            ->delete();

        if ($deleted === 0) {
            return DeletePriceAlertResult::NOT_DELETABLE;
        }

        try {
            $this->priceAlertIndex->remove(
                alertId: $alert->id,
                direction: $alert->direction,
            );
        } catch (Throwable $e) {
            report($e);
        }

        return DeletePriceAlertResult::DELETED;
    }
}
