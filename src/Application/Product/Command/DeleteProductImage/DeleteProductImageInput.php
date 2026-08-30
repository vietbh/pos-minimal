<?php

declare(strict_types=1);

namespace App\Application\Product\Command\DeleteProductImage;

final readonly class DeleteProductImageInput
{
    public function __construct(
        public int $productImageId,
        public int $actorId,
    ) {
        if ($this->productImageId <= 0 || $this->actorId <= 0) {
            throw new \InvalidArgumentException('IDs must be positive.');
        }
    }
}
