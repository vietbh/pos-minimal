<?php

declare(strict_types=1);

namespace App\Domain\Debt\Enum;

enum DebtStatus: string
{
    case OPEN = 'OPEN';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    public function isPartiallyPaid(): bool
    {
        return $this === self::PARTIALLY_PAID;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isClosed(): bool
    {
        return $this === self::PAID;
    }
}
