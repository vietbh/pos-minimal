<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetDraftOrder;

use App\Domain\Order\Enum\OrderStatus;

final readonly class DraftOrderResult
{
    /**
     * @param list<DraftOrderItemResult> $items
     */
    public function __construct(
        public int $id,
        public string $orderNumber,
        public OrderStatus $status,
        public ?DraftOrderCustomerResult $customer,
        public array $items,
        public int $subtotal,
        public int $total,
    ) {
    }
}
