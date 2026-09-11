<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query;

use App\Application\Statistics\Query\Model\DashboardResult;

final readonly class GetDashboardHandler
{
    public function __construct(private StatisticsQueryRepositoryInterface $repository) {}

    public function __invoke(StatisticsQueryInput $input): DashboardResult
    {
        return new DashboardResult(
            sales: $this->repository->getSalesSummary($input),
            payments: $this->repository->getPaymentBreakdown($input),
            debt: $this->repository->getDebtSummary($input),
            topProducts: $this->repository->getTopProducts($input),
            topCustomers: $this->repository->getTopCustomers($input),
            stock: $this->repository->getStockSnapshot(),
            from: $input->from,
            toExclusive: $input->toExclusive,
        );
    }
}
