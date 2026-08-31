<?php

declare(strict_types=1);

namespace App\Application\Product\Command\AdjustStock;

final readonly class AdjustStockInput
{
    public function __construct(
        public int $productId,
        public int $quantityChange,
        public ?string $reason,
        public string $idempotencyKey,
    ) {
    }
}
