<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query\Model;

final readonly class SalesSummary
{
    public function __construct(
        public string $grossSales,
        public string $netSales,
        public string $paymentsCollected,
        public string $cancelledAmount,
        public string $refundedAmount,
        public int $qualifyingOrders,
        public int $cancelledOrders,
        public int $refundedOrders,
        public string $averageOrderValue,
    ) {}
}
