<?php

declare(strict_types=1);

namespace App\Application\Product\Image;

final readonly class ValidatedImage
{
    public function __construct(
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public int $size,
    ) {
    }
}
