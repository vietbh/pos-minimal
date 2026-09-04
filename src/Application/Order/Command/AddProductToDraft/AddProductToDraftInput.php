<?php

declare(strict_types=1);

namespace App\Application\Order\Command\AddProductToDraft;

final readonly class AddProductToDraftInput
{
    public function __construct(
        public int $orderId,
        public int $productId,
        public int $quantity,
    ) {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException(
                'Order ID must be greater than zero.',
            );
        }

        if ($productId <= 0) {
            throw new \InvalidArgumentException(
                'Product ID must be greater than zero.',
            );
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Quantity must be greater than zero.',
            );
        }
    }
}
