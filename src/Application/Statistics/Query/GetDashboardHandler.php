<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query;

use App\Application\Statistics\Query\Model\DashboardResult;

final readonly class GetDashboardHandler
{
    public function __construct(private StatisticsQueryRepositoryInterface $repository) {}

    /** Initial request: load only the overview data. Other groups are loaded on demand. */
    public function __invoke(StatisticsQueryInput $input): DashboardResult
    {
        return new DashboardResult(
            sales: $this->repository->getSalesSummary($input),
            payments: [],
            debt: new \App\Application\Statistics\Query\Model\DebtSummary(0, '0.00', '0.00', '0.00'),
            topProducts: [],
            topCustomers: [],
            stock: $this->repository->getStockSnapshot(),
            weeklySales: $this->repository->getWeeklySales($input),
            from: $input->from,
            toExclusive: $input->toExclusive,
        );
    }
}
