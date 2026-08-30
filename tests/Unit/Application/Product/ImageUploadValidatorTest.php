<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Product;

use App\Application\Product\Image\ImageUpload;
use App\Application\Product\Image\ImageUploadException;
use App\Application\Product\Image\ImageUploadValidator;
use PHPUnit\Framework\TestCase;

final class ImageUploadValidatorTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'pos-image-') ?: throw new \RuntimeException('Unable to create temp file.');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testValidPngIsAccepted(): void
    {
        file_put_contents($this->file, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        $result = (new ImageUploadValidator())->validate(new ImageUpload(
            path: $this->file,
            originalFilename: 'product.png',
            clientMimeType: 'image/png',
            size: filesize($this->file) ?: 0,
        ));

        self::assertSame('image/png', $result->mimeType);
        self::assertSame('png', $result->extension);
        self::assertSame(1, $result->width);
        self::assertSame(1, $result->height);
    }

    public function testInvalidContentIsRejectedEvenWhenFilenameLooksLikeImage(): void
    {
        file_put_contents($this->file, 'not an image');

        $this->expectException(ImageUploadException::class);
        $this->expectExceptionMessage('Unsupported image type.');

        (new ImageUploadValidator())->validate(new ImageUpload(
            path: $this->file,
            originalFilename: 'evil.jpg',
            clientMimeType: 'image/jpeg',
            size: filesize($this->file) ?: 0,
        ));
    }
}
