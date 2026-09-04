<?php

declare(strict_types=1);

namespace App\Application\Order\Command\AddProductToDraft;

final readonly class AddProductToDraftResult
{
    /**
     * @param array{
     *     id: int|null,
     *     productId: int,
     *     productName: string,
     *     sku: string|null,
     *     unitPrice: string,
     *     quantity: int,
     *     subtotal: string
     * } $item
     */
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $status,
        public array $item,
        public string $subtotal,
        public string $total,
    ) {
    }
}
