<?php

declare(strict_types=1);

namespace App\Application\Order\Command\ChangeDraftItemQuantity;

final readonly class ChangeDraftItemQuantityInput
{
    public function __construct(
        public int $orderId,
        public int $itemId,
        public int $quantity,
    ) {
    }
}
