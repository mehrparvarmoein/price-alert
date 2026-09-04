<?php

namespace App\Domain\PriceAlert\Contracts;

use App\Models\PriceAlert;

interface AlertNotificationSender
{
    public function send(PriceAlert $alert): void;
}
