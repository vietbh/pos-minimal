<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enum;

enum PaymentReferenceStatus: string
{
    case PENDING = 'PENDING';
    case MATCHED = 'MATCHED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
