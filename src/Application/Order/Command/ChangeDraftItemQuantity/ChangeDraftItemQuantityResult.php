<?php

declare(strict_types=1);

namespace App\Application\Order\Command\ChangeDraftItemQuantity;

use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Shared\ValueObject\Money;

final readonly class ChangeDraftItemQuantityResult
{
    /**
     * @param array{
     *     id: int,
     *     productId: int,
     *     productName: string,
     *     sku: ?string,
     *     unitPrice: string,
     *     quantity: int,
     *     subtotal: string
     * } $item
     */
    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public OrderStatus $status,
        public array $item,
        public Money $subtotal,
        public Money $total,
    ) {
    }
}
