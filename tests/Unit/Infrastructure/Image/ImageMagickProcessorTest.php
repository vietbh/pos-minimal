<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Image;

use App\Infrastructure\Image\ImageMagickProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

final class ImageMagickProcessorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/pos-image-test-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testCreatesWebpVariants(): void
    {
        $finder = new ExecutableFinder();
        $binary = $finder->find('convert');

        if ($binary === null) {
            self::markTestSkipped('ImageMagick is not installed.');
        }

        $source = $this->directory . '/source.png';
        $output = $this->directory . '/variants';
        mkdir($output, 0775, true);

        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        $result = (new ImageMagickProcessor($binary))->process($source, $output);

        self::assertFileExists($result['thumbnail']);
        self::assertFileExists($result['medium']);
        self::assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->file($result['thumbnail']));
        self::assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->file($result['medium']));
    }
}
