<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

final readonly class OrderItemResult
{
    public function __construct(
        public int $id,
        public int $productId,
        public string $productName,
        public ?string $sku,
        public string $unitPrice,
        public int $quantity,
        public string $subtotal,
    ) {
    }
}
