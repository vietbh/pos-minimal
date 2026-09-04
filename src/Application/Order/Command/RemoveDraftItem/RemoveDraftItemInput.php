<?php

declare(strict_types=1);

namespace App\Application\Order\Command\RemoveDraftItem;

final readonly class RemoveDraftItemInput
{
    public function __construct(
        public int $orderId,
        public int $itemId,
    ) {
    }
}
