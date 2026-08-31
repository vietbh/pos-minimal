<?php

declare(strict_types=1);

namespace App\Application\Product\Command\DeactivateProduct;

final readonly class DeactivateProductInput
{
    public function __construct(
        public int $productId,
    ) {
    }
}
