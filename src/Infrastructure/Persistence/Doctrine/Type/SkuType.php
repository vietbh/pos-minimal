<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Product\ValueObject\Sku;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

final class SkuType extends StringType
{
    public const NAME = 'sku';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Sku) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                self::NAME,
                [Sku::class],
            );
        }

        return $value->value();
    }

    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?Sku {
        if ($value === null || $value instanceof Sku) {
            return $value;
        }

        try {
            return new Sku((string) $value);
        } catch (\Throwable $exception) {
            throw ConversionException::conversionFailed(
                $value,
                self::NAME,
                $exception,
            );
        }
    }

    public function requiresSQLCommentHint(
        AbstractPlatform $platform,
    ): bool {
        return true;
    }
}
