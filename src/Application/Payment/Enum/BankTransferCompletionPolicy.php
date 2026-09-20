<?php

declare(strict_types=1);

namespace App\Application\Payment\Enum;

enum BankTransferCompletionPolicy: string
{
    case MANUAL = 'MANUAL';
    case AUTO = 'AUTO';
}
