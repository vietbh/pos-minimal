<?php

declare(strict_types=1);

namespace App\Application\Order\Query\ListOrders;

use App\Domain\Order\Enum\OrderStatus;

final readonly class OrderListItemResult
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public OrderStatus $status,
        public ?int $customerId,
        public ?string $customerName,
        public string $total,
        public string $paidAmount,
        public string $debtAmount,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
