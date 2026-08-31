<?php

declare(strict_types=1);

namespace App\Application\Product\Command\UpdateProduct;

use App\Domain\Product\ValueObject\Sku;
use App\Domain\Shared\ValueObject\Money;

final readonly class UpdateProductInput
{
    public function __construct(
        public int $productId,
        public string $name,
        public ?Sku $sku,
        public ?string $unit,
        public ?Money $costPrice,
        public int $lowStockThreshold,
        public ?string $note,
    ) {
    }
}
