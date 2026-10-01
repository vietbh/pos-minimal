<?php

declare(strict_types=1);

namespace App\Application\Statistics\Goal;

final readonly class KpiGoalView
{
    public function __construct(
        public int $id,
        public string $metric,
        public string $direction,
        public string $targetValue,
        public string $initialValue,
        public string $actualValue,
        public float $progressPercent,
        public string $remainingValue,
        public ?int $estimatedDaysRemaining,
        public ?\DateTimeImmutable $estimatedDate,
        public bool $reached,
        public bool $expired,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public ?int $salesPointId,
        public ?string $salesPointName,
    ) {}
}
