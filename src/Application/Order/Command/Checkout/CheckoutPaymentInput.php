<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Shared\ValueObject\Money;

final readonly class CheckoutPaymentInput
{
    public function __construct(
        public PaymentMethod $method,
        public Money $amount,
    ) {
    }
}
