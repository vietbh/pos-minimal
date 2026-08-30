<?php

declare(strict_types=1);

namespace App\Application\Product\Message;

final readonly class ProcessProductImage
{
    public function __construct(
        public int $productImageId,
    ) {
        if ($this->productImageId <= 0) {
            throw new \InvalidArgumentException('Product image ID must be positive.');
        }
    }
}
