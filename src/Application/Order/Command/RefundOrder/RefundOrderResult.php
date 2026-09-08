<?php

declare(strict_types=1);

namespace App\Application\Order\Command\RefundOrder;

use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Shared\ValueObject\Money;

final readonly class RefundOrderResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public OrderStatus $status,
        public Money $refundedAmount,
        public int $restoredQuantity,
    ) {
    }
}
