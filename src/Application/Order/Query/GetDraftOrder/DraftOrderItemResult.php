<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetDraftOrder;

final readonly class DraftOrderItemResult
{
    public function __construct(
        public int $id,
        public int $productId,
        public string $productName,
        public ?string $sku,
        public int $unitPrice,
        public int $quantity,
        public int $subtotal,
    ) {
    }
}
