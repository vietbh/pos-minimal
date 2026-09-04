<?php

declare(strict_types=1);

namespace App\Application\Order\Command\AttachCustomerToDraft;

use App\Domain\Order\Enum\OrderStatus;

final readonly class AttachCustomerToDraftResult
{
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public OrderStatus $status,
        public int $customerId,
        public string $customerName,
        public ?string $customerPhone,
    ) {
    }
}
