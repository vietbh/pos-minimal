<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

final readonly class CheckoutItemInput
{
    /** @param array<string,string> $selectedAttributes */
    public function __construct(
        public int $productId,
        public int $quantity,
        public array $selectedAttributes = [],
    ) {}
}
