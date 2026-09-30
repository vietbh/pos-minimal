<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query\Model;

final readonly class RevenueTrend
{
    /** @param list<RevenueTrendPoint> $points */
    public function __construct(
        public array $points,
        public string $total,
        public ?string $peakLabel,
        public ?string $peakAmount,
    ) {
    }
}
