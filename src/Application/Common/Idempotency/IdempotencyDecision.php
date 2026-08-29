<?php

declare(strict_types=1);

namespace App\Application\Common\Idempotency;

use App\Domain\Idempotency\IdempotencyRecord;

final readonly class IdempotencyDecision
{
    private function __construct(
        public IdempotencyDecisionType $type,
        public ?IdempotencyRecord $record = null,
    ) {
    }

    public static function execute(
        IdempotencyRecord $record,
    ): self {
        return new self(
            type: IdempotencyDecisionType::EXECUTE,
            record: $record,
        );
    }

    public static function replay(
        IdempotencyRecord $record,
    ): self {
        return new self(
            type: IdempotencyDecisionType::REPLAY,
            record: $record,
        );
    }

    public static function inProgress(
        IdempotencyRecord $record,
    ): self {
        return new self(
            type: IdempotencyDecisionType::IN_PROGRESS,
            record: $record,
        );
    }
}
