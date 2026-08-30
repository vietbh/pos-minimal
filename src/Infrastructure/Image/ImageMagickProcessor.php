<?php

declare(strict_types=1);

namespace App\Infrastructure\Image;

use App\Application\Product\Image\ImageProcessorInterface;
use Symfony\Component\Process\Process;

final class ImageMagickProcessor implements ImageProcessorInterface
{
    public function __construct(
        private readonly string $binary,
    ) {
    }

    public function process(string $sourcePath, string $outputDirectory): array
    {
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Source image does not exist.');
        }

        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new \RuntimeException('Unable to create image output directory.');
        }

        $thumbnail = $outputDirectory . DIRECTORY_SEPARATOR . 'thumbnail.webp';
        $medium = $outputDirectory . DIRECTORY_SEPARATOR . 'medium.webp';

        $this->run([
            $this->binary,
            $sourcePath,
            '-auto-orient',
            '-strip',
            '-resize',
            '400x400>',
            '-quality',
            '82',
            $thumbnail,
        ]);

        $this->run([
            $this->binary,
            $sourcePath,
            '-auto-orient',
            '-strip',
            '-resize',
            '1200x1200>',
            '-quality',
            '84',
            $medium,
        ]);

        return [
            'thumbnail' => $thumbnail,
            'medium' => $medium,
        ];
    }

    /** @param list<string> $command */
    private function run(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                trim($process->getErrorOutput()) ?: 'Image processing failed.',
            );
        }
    }
}
