<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Kpi\KpiGoal;
use App\Domain\Kpi\Repository\KpiGoalRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class KpiGoalRepository implements KpiGoalRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(int $id): ?KpiGoal
    {
        $goal = $this->entityManager->find(KpiGoal::class, $id);

        return $goal instanceof KpiGoal ? $goal : null;
    }

    /** @return list<KpiGoal> */
    public function findActiveAndRecent(int $limit): array
    {
        $limit = max(1, min($limit, 100));

        $result = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(KpiGoal::class, 'g')
            ->orderBy('g.endDate', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $result,
            static fn (mixed $goal): bool => $goal instanceof KpiGoal,
        ));
    }
}
