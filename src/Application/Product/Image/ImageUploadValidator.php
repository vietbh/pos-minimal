<?php

declare(strict_types=1);

namespace App\Application\Product\Image;

use App\Application\Product\Image\ImageUploadException;

final class ImageUploadValidator
{
    public const MAX_SIZE = 10 * 1024 * 1024;
    public const MAX_WIDTH = 4000;
    public const MAX_HEIGHT = 4000;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function validate(ImageUpload $upload): ValidatedImage
    {
        $actualSize = filesize($upload->path);
        if ($actualSize === false || $actualSize <= 0) {
            throw new ImageUploadException('INVALID_IMAGE', 'The uploaded file cannot be inspected.');
        }

        if ($actualSize > self::MAX_SIZE) {
            throw new ImageUploadException('IMAGE_TOO_LARGE', 'Image exceeds the maximum allowed size.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($upload->path);

        if (!is_string($mimeType) || !isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new ImageUploadException('INVALID_IMAGE_TYPE', 'Unsupported image type.');
        }

        $imageInfo = @getimagesize($upload->path);
        if ($imageInfo === false) {
            throw new ImageUploadException('INVALID_IMAGE', 'The uploaded file is not a valid image.');
        }

        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;

        if ($width <= 0 || $height <= 0 || $width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            throw new ImageUploadException('IMAGE_DIMENSIONS_INVALID', 'Image dimensions are not supported.');
        }

        return new ValidatedImage(
            mimeType: $mimeType,
            extension: self::ALLOWED_MIME_TYPES[$mimeType],
            width: $width,
            height: $height,
            size: $actualSize,
        );
    }
}
