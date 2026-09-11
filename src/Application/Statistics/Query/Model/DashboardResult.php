<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query\Model;
final readonly class DashboardResult
{
    public function __construct(
        public SalesSummary $sales,
        /** @var list<PaymentBreakdown> */ public array $payments,
        public DebtSummary $debt,
        /** @var list<TopProduct> */ public array $topProducts,
        /** @var list<TopCustomer> */ public array $topCustomers,
        public StockSnapshot $stock,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $toExclusive,
    ) {}
}
