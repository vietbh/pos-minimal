<?php

declare(strict_types=1);

namespace App\Domain\Stock\Enum;

enum StockMovementType: string
{
    case INITIAL = 'INITIAL';
    case SALE = 'SALE';
    case SALE_REVERSAL = 'SALE_REVERSAL';
    case ADJUSTMENT = 'ADJUSTMENT';

    public function isSale(): bool
    {
        return $this === self::SALE;
    }

    public function isSaleReversal(): bool
    {
        return $this === self::SALE_REVERSAL;
    }

    public function isAdjustment(): bool
    {
        return $this === self::ADJUSTMENT;
    }

    public function isInitial(): bool
    {
        return $this === self::INITIAL;
    }
}
