<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Statistics;

use App\Application\Statistics\Query\Period\StatisticsPeriodResolver;
use PHPUnit\Framework\TestCase;

final class StatisticsPeriodResolverTest extends TestCase
{
    private StatisticsPeriodResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new StatisticsPeriodResolver('Asia/Ho_Chi_Minh');
    }

    public function testTodayIsApplicationTimezoneBounded(): void
    {
        $period = $this->resolver->resolve('today', null, null);

        self::assertSame('today', $period->preset);
        self::assertSame('00:00:00', $period->from->format('H:i:s'));
        self::assertSame('00:00:00', $period->toExclusive->format('H:i:s'));
        self::assertSame(1, $period->from->diff($period->toExclusive)->days);
        self::assertSame('Asia/Ho_Chi_Minh', $period->from->getTimezone()->getName());
    }

    public function testYesterdayEndsAtTodayStart(): void
    {
        $period = $this->resolver->resolve('yesterday', null, null);

        self::assertSame('yesterday', $period->preset);
        self::assertSame($period->from->modify('+1 day')->format('Y-m-d'), $period->toExclusive->format('Y-m-d'));
    }

    public function testWeekStartsOnMonday(): void
    {
        $period = $this->resolver->resolve('week', null, null);

        self::assertSame('1', $period->from->format('N'));
        self::assertGreaterThanOrEqual(1, $period->from->diff($period->toExclusive)->days);
        self::assertLessThanOrEqual(7, $period->from->diff($period->toExclusive)->days);
    }

    public function testMonthUsesCalendarBoundaries(): void
    {
        $period = $this->resolver->resolve('month', null, null);

        self::assertSame('01', $period->from->format('d'));
        self::assertSame('01', $period->toExclusive->format('d'));
        self::assertSame('month', $period->preset);
    }

    public function testCustomRangeIsInclusiveByDate(): void
    {
        $period = $this->resolver->resolve('custom', '2026-09-10', '2026-09-12');

        self::assertSame('2026-09-10', $period->fromValue);
        self::assertSame('2026-09-12', $period->toValue);
        self::assertSame('2026-09-13', $period->toExclusive->format('Y-m-d'));
    }

    public function testInvalidPresetIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->resolve('last-year', null, null);
    }

    public function testInvalidDateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->resolve('custom', '2026-02-30', '2026-03-01');
    }
}
