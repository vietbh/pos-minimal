<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enum;

enum CheckoutPaymentSessionStatus: string
{
    case WAITING_FOR_BANK_PAYMENT = 'WAITING_FOR_BANK_PAYMENT';
    case PAID = 'PAID';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
