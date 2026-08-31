<?php

declare(strict_types=1);

namespace App\Application\Product\Command\AdjustStock;

final readonly class AdjustStockResult
{
    public function __construct(
        public int $productId,
        public int $quantityBefore,
        public int $quantityChange,
        public int $quantityAfter,
        public int $stockMovementId,
    ) {
    }
}
