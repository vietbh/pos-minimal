<?php

declare(strict_types=1);

namespace App\Application\Product\Command\UploadProductImage;

use App\Application\Product\Image\ImageUpload;

final readonly class UploadProductImageInput
{
    public function __construct(
        public int $productId,
        public ImageUpload $upload,
        public int $actorId,
    ) {
        if ($this->productId <= 0 || $this->actorId <= 0) {
            throw new \InvalidArgumentException('Product and actor IDs must be positive.');
        }
    }
}
