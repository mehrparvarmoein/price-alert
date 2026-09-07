<?php

namespace App\Domain\PriceAlert\Enums;

enum DeletePriceAlertResult
{
    case DELETED;

    case NOT_FOUND;

    case NOT_DELETABLE;
}
