<?php

declare(strict_types=1);

namespace App\Application\Product\Command\ChangeProductPrice;

use App\Domain\Shared\ValueObject\Money;

final readonly class ChangeProductPriceInput
{
    public function __construct(
        public int $productId,
        public Money $sellingPrice,
    ) {
    }
}
