<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CancelOrder;

use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Shared\ValueObject\Money;

final readonly class CancelOrderResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public OrderStatus $status,
        public Money $reversedAmount,
        public int $restoredQuantity,
    ) {
    }
}
