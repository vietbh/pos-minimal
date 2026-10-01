<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Kpi;

use App\Domain\Kpi\KpiGoal;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class KpiGoalTest extends TestCase
{
    public function testCreatesRevenueGoal(): void
    {
        $user = new User('kpi@example.test');
        $goal = new KpiGoal(
            KpiGoal::METRIC_REVENUE,
            KpiGoal::DIRECTION_AT_LEAST,
            '100000000',
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable('2026-09-30'),
            $user,
        );

        self::assertSame('revenue', $goal->getMetric());
        self::assertSame('100000000.00', $goal->getTargetValue());
        self::assertSame('2026-09-01', $goal->getStartDate()->format('Y-m-d'));
    }

    public function testDebtGoalMustBeAtMost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new KpiGoal(
            KpiGoal::METRIC_OUTSTANDING_DEBT,
            KpiGoal::DIRECTION_AT_LEAST,
            '10000000',
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable('2026-09-30'),
            new User('kpi-debt@example.test'),
        );
    }
}
