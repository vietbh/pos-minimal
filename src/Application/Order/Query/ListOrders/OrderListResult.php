<?php

declare(strict_types=1);

namespace App\Application\Order\Query\ListOrders;

final readonly class OrderListResult
{
    /** @param list<OrderListItemResult> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $totalItems,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->totalItems / $this->perPage));
    }
}
