<?php

declare(strict_types=1);

namespace App\Application\Common\Idempotency;

final readonly class IdempotencyReservation
{
    public function __construct(
        public IdempotencyDecision $decision,
        public bool $acquired,
    ) {
    }

    public static function acquired(
        IdempotencyDecision $decision,
    ): self {
        return new self(
            decision: $decision,
            acquired: true,
        );
    }

    public static function existing(
        IdempotencyDecision $decision,
    ): self {
        return new self(
            decision: $decision,
            acquired: false,
        );
    }
}
