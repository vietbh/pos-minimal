<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query\Model;

final readonly class RevenueTrendPoint
{
    public function __construct(
        public string $dateLabel,
        public string $amount,
        public string $amountMillions,
        public int $barPercent,
    ) {
        if ($this->barPercent < 0 || $this->barPercent > 100) {
            throw new \InvalidArgumentException('Revenue trend bar percent must be between 0 and 100.');
        }
    }
}
