<?php

declare(strict_types=1);
namespace App\Tests\Unit\Application;

use App\Application\Statistics\Query\StatisticsQueryInput;
use PHPUnit\Framework\TestCase;

final class StatisticsQueryInputTest extends TestCase
{
    public function testValidRange(): void
    {
        $input=new StatisticsQueryInput(new \DateTimeImmutable('2026-09-01'),new \DateTimeImmutable('2026-09-11'),10);
        self::assertSame(10,$input->limit);
    }

    public function testRejectsInvalidRangeAndLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StatisticsQueryInput(new \DateTimeImmutable('2026-09-11'),new \DateTimeImmutable('2026-09-11'),10);
    }

    public function testRejectsRangeOver366Days(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StatisticsQueryInput(new \DateTimeImmutable('2026-01-03'),new \DateTimeImmutable('2026-01-02'),10);
    }
}
