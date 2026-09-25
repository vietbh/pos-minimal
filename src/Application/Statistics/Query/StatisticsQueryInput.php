<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query;

final readonly class StatisticsQueryInput
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $toExclusive,
        public int $limit = 10,
        public ?int $salesPointId = null,
    ) {
        if ($this->from >= $this->toExclusive) {
            throw new \InvalidArgumentException('Statistics date range must be non-empty.');
        }
        if ($this->salesPointId !== null && $this->salesPointId <= 0) { throw new \InvalidArgumentException('Sales point filter must be greater than zero.'); }
        if ($this->limit < 1 || $this->limit > 100) {
            throw new \InvalidArgumentException('Statistics limit must be between 1 and 100.');
        }
        if ($this->from->diff($this->toExclusive)->days > 366) {
            throw new \InvalidArgumentException('Statistics date range cannot exceed 366 days.');
        }
    }
}
