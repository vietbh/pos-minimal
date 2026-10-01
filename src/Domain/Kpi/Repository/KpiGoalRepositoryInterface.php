<?php

declare(strict_types=1);

namespace App\Domain\Kpi\Repository;

use App\Domain\Kpi\KpiGoal;

interface KpiGoalRepositoryInterface
{
    /** @return list<KpiGoal> */
    public function findActiveAndRecent(int $limit): array;
    public function findById(int $id): ?KpiGoal;
}
