<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Shared\ValueObject\Money;

final readonly class CheckoutResult
{
    public function __construct(
        public ?int $orderId,
        public ?string $orderNumber,
        public Money $total,
        public Money $subtotal,
        public Money $discount,
        public Money $paidAmount,
        public Money $debtAmount,
        public Money $tenderedAmount,
        public Money $changeAmount,
        public ?OrderStatus $status,
        public ?string $paymentReference = null,
        public ?string $paymentReferenceExpiresAt = null,
        public ?string $paymentReferenceTransferContent = null,
        public ?string $paymentReferenceQrUrl = null,
        public ?string $bankTransferCompletionPolicy = null,
        public ?int $paymentSessionId = null,
    ) {
    }
}
