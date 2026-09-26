<?php

declare(strict_types=1);

namespace App\Application\Order\Query\CopyOrderToPos;

final readonly class CopyOrderToPosResult
{
    /** @param list<CopyOrderToPosItemResult> $items */
    public function __construct(
        public int $sourceOrderId,
        public string $sourceOrderNumber,
        public ?int $customerId,
        public ?string $customerName,
        public ?string $customerPhone,
        public int $customerDefaultDiscountPercent,
        public ?string $cashTenderedAmount,
        public array $items,
    ) {
    }
}
