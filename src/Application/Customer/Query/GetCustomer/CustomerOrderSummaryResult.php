<?php

declare(strict_types=1);

namespace App\Application\Customer\Query\GetCustomer;

use App\Domain\Order\Enum\OrderStatus;

final readonly class CustomerOrderSummaryResult
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public OrderStatus $status,
        public string $total,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
