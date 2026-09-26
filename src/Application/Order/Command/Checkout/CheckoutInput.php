<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

use App\Domain\Shared\ValueObject\Money;

final readonly class CheckoutInput
{
    /**
     * @param list<CheckoutItemInput> $items
     */
    public function __construct(
        public array $items,
        public ?int $customerId,
        public CheckoutPaymentInput $payment,
        public string $idempotencyKey,
        public ?int $bankAccountId = null,
        public ?string $paymentReference = null,
        public ?string $note = null,
        public ?int $salesPointId = null,
        public ?Money $manualDiscount = null,
    ) {
    }
}
