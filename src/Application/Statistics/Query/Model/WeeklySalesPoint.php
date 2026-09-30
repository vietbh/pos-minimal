<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query\Model;

final readonly class WeeklySalesPoint
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $label,
        public string $netSales,
        public string $displayMillions,
        public int $barPercent,
    ) {
        if ($barPercent < 0 || $barPercent > 100) {
            throw new \InvalidArgumentException('Chart bar percent must be between 0 and 100.');
        }
    }
}
