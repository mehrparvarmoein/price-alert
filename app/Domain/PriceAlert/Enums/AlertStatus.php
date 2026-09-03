<?php

namespace App\Domain\PriceAlert\Enums;

enum AlertStatus: string
{
    case ACTIVE = 'active';
    case PROCESSING = 'processing';
    case TRIGGERED = 'triggered';
}
