<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query;

use App\Application\Statistics\Query\Model\{DashboardResult,DebtSummary,PaymentBreakdown,SalesSummary,StockSnapshot,TopCustomer,TopProduct};

interface StatisticsQueryRepositoryInterface
{
    public function getSalesSummary(StatisticsQueryInput $input): SalesSummary;
    /** @return list<PaymentBreakdown> */
    public function getPaymentBreakdown(StatisticsQueryInput $input): array;
    public function getDebtSummary(StatisticsQueryInput $input): DebtSummary;
    /** @return list<TopProduct> */
    public function getTopProducts(StatisticsQueryInput $input): array;
    /** @return list<TopCustomer> */
    public function getTopCustomers(StatisticsQueryInput $input): array;
    public function getStockSnapshot(): StockSnapshot;
}
