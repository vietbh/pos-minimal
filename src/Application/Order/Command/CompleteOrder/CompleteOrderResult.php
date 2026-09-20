<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CompleteOrder;

final readonly class CompleteOrderResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $status,
        public string $paidAmount,
        public string $debtAmount,
        public string $total,
    ) {
    }
}
