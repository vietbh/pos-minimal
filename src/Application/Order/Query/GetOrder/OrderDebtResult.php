<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

final readonly class OrderDebtResult
{
    public function __construct(
        public int $id,
        public string $originalAmount,
        public string $paidAmount,
        public string $remainingAmount,
        public string $status,
    ) {
    }
}
