<?php

declare(strict_types=1);

namespace App\Domain\Order\Enum;

enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED,
            self::CANCELLED => true,
            self::DRAFT => false,
        };
    }
}
