<?php

declare(strict_types=1);

namespace App\Application\Order\Command\RefundOrder;

final readonly class RefundOrderInput
{
    public function __construct(
        public int $orderId,
        public string $reason,
        public string $idempotencyKey,
    ) {
    }
}
