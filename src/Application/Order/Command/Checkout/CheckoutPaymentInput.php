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
        public ?Money $tenderedAmount = null,
    ) {
        if (
            $this->method !== PaymentMethod::CASH
            && $this->tenderedAmount !== null
        ) {
            throw new \InvalidArgumentException(
                'Tendered amount is only supported for cash payments.',
            );
        }

    }
}
