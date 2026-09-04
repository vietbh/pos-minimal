<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CreateDraftOrder;

final readonly class CreateDraftOrderInput
{
    public function __construct(
        public int $userId,
        public ?int $customerId = null,
        public ?string $note = null,
    ) {
    }
}
