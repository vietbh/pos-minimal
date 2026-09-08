<?php

declare(strict_types=1);

namespace App\Domain\Order\Enum;

enum FinancialReversalType: string
{
    case CANCEL = 'CANCEL';
    case REFUND = 'REFUND';
}
