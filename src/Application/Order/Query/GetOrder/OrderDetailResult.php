<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

use App\Domain\Order\Enum\OrderStatus;

final readonly class OrderDetailResult
{
    /** @param list<OrderItemResult> $items */
    /** @param list<OrderPaymentResult> $payments */
    public function __construct(
        public int $id,
        public string $orderNumber,
        public OrderStatus $status,
        public string $customerName,
        public ?string $customerPhone,
        public string $createdBy,
        public string $subtotal,
        public string $discount,
        public int $discountPercent,
        public string $manualDiscount,
        public string $total,
        public string $paidAmount,
        public string $debtAmount,
        public ?string $note,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $completedAt,
        public ?\DateTimeImmutable $cancelledAt,
        public array $items,
        public array $payments,
        public ?OrderExternalTransactionResult $externalTransaction,
        public ?OrderDebtResult $debt,
    ) {
    }
}
