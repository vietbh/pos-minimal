<?php

declare(strict_types=1);

namespace App\Application\Order\Command\DetachCustomerFromDraft;

use App\Domain\Order\Enum\OrderStatus;

final readonly class DetachCustomerFromDraftResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public OrderStatus $status,
        public bool $customerDetached,
    ) {
    }
}
