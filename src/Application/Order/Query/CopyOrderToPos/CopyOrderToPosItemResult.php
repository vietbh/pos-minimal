<?php

declare(strict_types=1);

namespace App\Application\Order\Query\CopyOrderToPos;

final readonly class CopyOrderToPosItemResult
{
    public function __construct(
        public int $productId,
        public string $name,
        public ?string $sku,
        public string $unitPrice,
        public int $quantity,
        public bool $active,
    ) {
    }
}
