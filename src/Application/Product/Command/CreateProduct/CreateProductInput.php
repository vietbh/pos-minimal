<?php

declare(strict_types=1);

namespace App\Application\Product\Command\CreateProduct;

use App\Domain\Product\ValueObject\Sku;
use App\Domain\Shared\ValueObject\Money;

final readonly class CreateProductInput
{
    public function __construct(
        public string $name,
        public Money $sellingPrice,
        public ?Sku $sku = null,
        public ?string $unit = null,
        public ?Money $costPrice = null,
        public int $lowStockThreshold = 0,
        public ?string $note = null,
    ) {
    }
}
