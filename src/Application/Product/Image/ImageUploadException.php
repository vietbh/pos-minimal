<?php

declare(strict_types=1);

namespace App\Application\Product\Image;

use App\Application\Common\Exception\ApplicationException;

final class ImageUploadException extends ApplicationException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
