<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CreateDraftOrder;

final readonly class CreateDraftOrderResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $status,
        public string $subtotal,
        public string $total,
    ) {
    }
}
