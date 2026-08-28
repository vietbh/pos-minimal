<?php

declare(strict_types=1);

namespace App\Domain\Product\Enum;

enum ImageStatus: string
{
    case UPLOADING = 'UPLOADING';
    case PROCESSING = 'PROCESSING';
    case READY = 'READY';
    case FAILED = 'FAILED';

    public function isUploading(): bool
    {
        return $this === self::UPLOADING;
    }

    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    public function isReady(): bool
    {
        return $this === self::READY;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::READY,
            self::FAILED => true,
            self::UPLOADING,
            self::PROCESSING => false,
        };
    }
}
