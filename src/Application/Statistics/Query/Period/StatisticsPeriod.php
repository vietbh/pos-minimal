<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query\Period;

final readonly class StatisticsPeriod
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $toExclusive,
        public string $fromValue,
        public string $toValue,
        public string $preset,
    ) {}
}
