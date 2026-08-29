<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

final readonly class CheckoutHandlerEntryPoint
{
    public function __construct(
        private CheckoutHandler $handler,
    ) {
    }

    public function handle(CheckoutInput $input): CheckoutResult
    {
        return ($this->handler)($input);
    }
}
