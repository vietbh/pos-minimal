<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Order\ValueObject\OrderNumber;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

final class OrderNumberType extends StringType
{
    public const NAME = 'order_number';

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

        if (!$value instanceof OrderNumber) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                self::NAME,
                [OrderNumber::class],
            );
        }

        return $value->value();
    }

    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?OrderNumber {
        if ($value === null || $value instanceof OrderNumber) {
            return $value;
        }

        try {
            return new OrderNumber((string) $value);
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
