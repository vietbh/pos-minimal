<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query\Model;

final readonly class WeeklySalesSummary
{
    /**
     * @param list<WeeklySalesPoint> $points
     */
    public function __construct(
        public array $points,
        public string $total,
        public string $highest,
    ) {
    }
}
