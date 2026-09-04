<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetDraftOrder;

final readonly class GetDraftOrderInput
{
    public function __construct(
        public int $orderId,
    ) {
    }
}
