<?php

declare(strict_types=1);

namespace App\Application\Product\Command\ChangeLowStockThreshold;

final readonly class ChangeLowStockThresholdInput
{
    public function __construct(
        public int $productId,
        public int $lowStockThreshold,
    ) {
    }
}
