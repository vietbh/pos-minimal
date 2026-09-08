<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

final readonly class OrderPaymentResult
{
    public function __construct(
        public int $id,
        public string $amount,
        public string $method,
        public ?string $reference,
        public string $username,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
