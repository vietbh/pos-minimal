<?php

declare(strict_types=1);

namespace App\Application\Product\Image;

interface ImageProcessorInterface
{
    /** @return array{thumbnail: string, medium: string} */
    public function process(string $sourcePath, string $outputDirectory): array;
}
