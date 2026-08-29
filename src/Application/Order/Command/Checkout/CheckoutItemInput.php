<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

final readonly class CheckoutItemInput
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {
    }
}
