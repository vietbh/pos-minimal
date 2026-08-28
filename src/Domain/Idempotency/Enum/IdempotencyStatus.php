<?php

declare(strict_types=1);

namespace App\Domain\Idempotency\Enum;

enum IdempotencyStatus: string
{
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';

    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED,
            self::FAILED => true,
            self::PROCESSING => false,
        };
    }


}
