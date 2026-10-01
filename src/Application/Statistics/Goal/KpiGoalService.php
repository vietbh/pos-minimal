<?php

declare(strict_types=1);

namespace App\Application\Statistics\Goal;

use App\Application\Statistics\Query\StatisticsQueryInput;
use App\Application\Statistics\Query\StatisticsQueryRepositoryInterface;
use App\Domain\Kpi\KpiGoal;
use App\Domain\Kpi\Repository\KpiGoalRepositoryInterface;
use App\Domain\User\User;
use App\Domain\SalesPoint\SalesPoint;
use Doctrine\ORM\EntityManagerInterface;

final readonly class KpiGoalService
{
    public function __construct(
        private KpiGoalRepositoryInterface $repository,
        private StatisticsQueryRepositoryInterface $statistics,
        private EntityManagerInterface $entityManager,
    ) {}

    /** @return list<KpiGoalView> */
    public function views(\DateTimeImmutable $now, ?int $salesPointId = null): array
    {
        $views = [];
        foreach ($this->repository->findActiveAndRecent(12) as $goal) {
            $goalSalesPointId = $goal->getSalesPoint()?->getId();
            if ($salesPointId !== null && $goalSalesPointId !== null && $goalSalesPointId !== $salesPointId) {
                continue;
            }
            $views[] = $this->buildView($goal, $now);
        }
        return $views;
    }

    public function viewById(int $id, \DateTimeImmutable $now, ?int $salesPointId = null): ?KpiGoalView
    {
        $goal = $this->repository->findById($id);
        if ($goal === null) {
            return null;
        }

        $goalSalesPointId = $goal->getSalesPoint()?->getId();
        if ($salesPointId !== null && $goalSalesPointId !== null && $goalSalesPointId !== $salesPointId) {
            return null;
        }

        return $this->buildView($goal, $now);
    }

    public function create(
        User $user,
        string $metric,
        string $direction,
        string $target,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $salesPointId = null,
    ): KpiGoal {
        $salesPoint = null;
        if ($salesPointId !== null) {
            $salesPoint = $this->entityManager->find(SalesPoint::class, $salesPointId);
            if (!$salesPoint instanceof SalesPoint || !$salesPoint->isActive()) {
                throw new \InvalidArgumentException('Sales point không tồn tại hoặc không hoạt động.');
            }
        }

        $initial = '0.00';
        if ($metric === KpiGoal::METRIC_OUTSTANDING_DEBT) {
            $initial = $this->statistics->getCurrentOutstandingDebt($salesPointId);
        }

        $goal = new KpiGoal($metric, $direction, $target, $start, $end, $user, $initial, $salesPoint);
        $this->entityManager->persist($goal);
        $this->entityManager->flush();
        return $goal;
    }

    public function delete(int $id): void
    {
        $goal = $this->repository->findById($id);
        if ($goal === null) {
            return;
        }
        $this->entityManager->remove($goal);
        $this->entityManager->flush();
    }

    private function buildView(KpiGoal $goal, \DateTimeImmutable $now): KpiGoalView
    {
        $effectiveTo = min($now->setTime(23, 59, 59), $goal->getEndDate()->setTime(23, 59, 59));
        $actual = $this->actualValue($goal, $effectiveTo);
        $target = (float) $goal->getTargetValue();
        $initial = (float) $goal->getInitialValue();
        $actualFloat = (float) $actual;

        if ($goal->getDirection() === KpiGoal::DIRECTION_AT_MOST) {
            $requiredReduction = max($initial - $target, 0.0);
            $achievedReduction = max($initial - $actualFloat, 0.0);
            $progress = $requiredReduction <= 0 ? ($actualFloat <= $target ? 100.0 : 0.0) : min(100.0, max(0.0, $achievedReduction / $requiredReduction * 100));
            $remaining = max($actualFloat - $target, 0.0);
        } else {
            $progress = $target <= 0 ? 0.0 : min(100.0, max(0.0, $actualFloat / $target * 100));
            $remaining = max($target - $actualFloat, 0.0);
        }

        $reached = $goal->getDirection() === KpiGoal::DIRECTION_AT_MOST
            ? $actualFloat <= $target
            : $actualFloat >= $target;
        $expired = $now->setTime(0, 0) > $goal->getEndDate();

        $daysElapsed = max(0.0, ($effectiveTo->getTimestamp() - $goal->getStartDate()->getTimestamp()) / 86400);
        $daysRemaining = null;
        $estimatedDate = null;
        if (!$reached && !$expired && $daysElapsed >= 1) {
            if ($goal->getDirection() === KpiGoal::DIRECTION_AT_MOST) {
                $change = $initial - $actualFloat;
                if ($change > 0 && $remaining > 0) {
                    $daysRemaining = (int) ceil($remaining / ($change / $daysElapsed));
                }
            } else {
                if ($actualFloat > 0 && $remaining > 0) {
                    $daysRemaining = (int) ceil($remaining / ($actualFloat / $daysElapsed));
                }
            }
            if ($daysRemaining !== null) {
                $candidate = $now->setTime(0, 0)->modify('+' . $daysRemaining . ' days');
                $estimatedDate = $candidate;
            }
        }

        return new KpiGoalView(
            $goal->getId() ?? 0,
            $goal->getMetric(),
            $goal->getDirection(),
            $goal->getTargetValue(),
            $goal->getInitialValue(),
            number_format($actualFloat, 2, '.', ''),
            round($progress, 1),
            number_format($remaining, 2, '.', ''),
            $daysRemaining,
            $estimatedDate,
            $reached,
            $expired,
            $goal->getStartDate(),
            $goal->getEndDate(),
            $goal->getSalesPoint()?->getId(),
            $goal->getSalesPoint()?->getName(),
        );
    }

    private function actualValue(KpiGoal $goal, \DateTimeImmutable $to): string
    {
        $toExclusive = min($to->modify('+1 second'), $goal->getEndDate()->modify('+1 day'));
        $goalSalesPointId = $goal->getSalesPoint()?->getId();
        $input = new StatisticsQueryInput($goal->getStartDate(), $toExclusive, 100, $goalSalesPointId);

        return match ($goal->getMetric()) {
            KpiGoal::METRIC_REVENUE => $this->statistics->getSalesSummary($input)->netSales,
            KpiGoal::METRIC_UNITS_SOLD => number_format($this->statistics->getUnitsSold($input), 2, '.', ''),
            KpiGoal::METRIC_CUSTOMERS => number_format($this->statistics->getUniqueCustomers($input), 2, '.', ''),
            KpiGoal::METRIC_OUTSTANDING_DEBT => $this->statistics->getCurrentOutstandingDebt($goalSalesPointId),
            default => '0.00',
        };
    }
}
