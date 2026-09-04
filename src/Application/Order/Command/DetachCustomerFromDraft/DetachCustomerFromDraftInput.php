<?php

declare(strict_types=1);

namespace App\Application\Order\Command\DetachCustomerFromDraft;

final readonly class DetachCustomerFromDraftInput
{
    public function __construct(
        public int $orderId,
    ) {
    }
}
