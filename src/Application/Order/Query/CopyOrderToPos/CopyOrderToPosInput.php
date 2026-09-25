<?php

declare(strict_types=1);

namespace App\Application\Order\Query\CopyOrderToPos;

final readonly class CopyOrderToPosInput
{
    public function __construct(public int $orderId)
    {
    }
}
