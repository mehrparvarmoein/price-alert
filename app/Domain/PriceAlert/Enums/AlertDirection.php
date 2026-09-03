<?php

namespace App\Domain\PriceAlert\Enums;

enum AlertDirection: string
{
    case ABOVE = 'above';
    case BELOW = 'below';
}
