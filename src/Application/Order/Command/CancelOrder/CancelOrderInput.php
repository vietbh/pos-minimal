<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CancelOrder;

final readonly class CancelOrderInput
{
    public function __construct(
        public int $orderId,
        public string $reason,
        public string $idempotencyKey,
    ) {
    }
}
