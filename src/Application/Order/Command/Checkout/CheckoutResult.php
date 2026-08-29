<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Shared\ValueObject\Money;

final readonly class CheckoutResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public Money $total,
        public Money $paidAmount,
        public Money $debtAmount,
        public OrderStatus $status,
    ) {
    }
}
