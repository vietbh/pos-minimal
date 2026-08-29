<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

final readonly class CheckoutInput
{
    /**
     * @param list<CheckoutItemInput> $items
     */
    public function __construct(
        public array $items,
        public ?int $customerId,
        public CheckoutPaymentInput $payment,
        public ?string $note,
        public string $idempotencyKey,
    ) {
    }
}
