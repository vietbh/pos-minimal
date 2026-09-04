<?php

declare(strict_types=1);

namespace App\Application\Order\Command\AttachCustomerToDraft;

final readonly class AttachCustomerToDraftInput
{
    public function __construct(
        public int $orderId,
        public int $customerId,
    ) {
    }
}
