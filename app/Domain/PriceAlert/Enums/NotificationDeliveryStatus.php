<?php

namespace App\Domain\PriceAlert\Enums;

enum NotificationDeliveryStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
