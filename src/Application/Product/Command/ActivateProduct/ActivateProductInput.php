<?php

declare(strict_types=1);

namespace App\Application\Product\Command\ActivateProduct;

final readonly class ActivateProductInput
{
    public function __construct(
        public int $productId,
    ) {
    }
}
