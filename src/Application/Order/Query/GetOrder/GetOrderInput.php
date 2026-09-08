<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

final readonly class GetOrderInput
{
    public function __construct(public int $orderId)
    {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException('Order ID must be greater than zero.');
        }
    }
}
