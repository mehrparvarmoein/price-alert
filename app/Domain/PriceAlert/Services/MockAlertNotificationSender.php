<?php

namespace App\Domain\PriceAlert\Services;

use App\Domain\PriceAlert\Contracts\AlertNotificationSender;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\Log;

class MockAlertNotificationSender implements AlertNotificationSender
{
    public function send(PriceAlert $alert): void
    {
        
        Log::info('Price alert notification sent', [
            'idempotency_key' => "price-alert:{$alert->id}",
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'target_price' => $alert->target_price,
        ]);
    }
}
