<?php

declare(strict_types=1);

namespace App\Application\Product\Image;

final readonly class ImageUpload
{
    public function __construct(
        public string $path,
        public string $originalFilename,
        public string $clientMimeType,
        public int $size,
    ) {
        if ($this->path === '' || !is_file($this->path) || !is_readable($this->path)) {
            throw new \InvalidArgumentException('Uploaded image file is not readable.');
        }

        if ($this->size <= 0) {
            throw new \InvalidArgumentException('Uploaded image size must be greater than zero.');
        }
    }
}
