<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

final readonly class OrderExternalTransactionResult
{
    public function __construct(
        public int $id,
        public string $provider,
        public string $externalTransactionId,
        public string $amount,
        public string $description,
        public ?string $reference,
        public \DateTimeImmutable $occurredAt,
        public string $status,
    ) {
    }
}
