<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enum;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case BANK_TRANSFER = 'BANK_TRANSFER';
}
