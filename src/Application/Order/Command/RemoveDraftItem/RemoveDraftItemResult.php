<?php

declare(strict_types=1);

namespace App\Application\Order\Command\RemoveDraftItem;

use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Shared\ValueObject\Money;

final readonly class RemoveDraftItemResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public OrderStatus $status,
        public int $remainingItemCount,
        public Money $subtotal,
        public Money $total,
    ) {
    }
}
