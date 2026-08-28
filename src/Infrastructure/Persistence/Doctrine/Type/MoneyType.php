<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Shared\ValueObject\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class MoneyType extends Type
{
    public const NAME = 'money';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string {
        return $platform->getDecimalTypeDeclarationSQL([
            'precision' => 15,
            'scale' => 2,
        ]);
    }

    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Money) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                self::NAME,
                [Money::class],
            );
        }

        return $value->toDecimal();
    }

    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?Money {
        if ($value === null || $value instanceof Money) {
            return $value;
        }

        try {
            return Money::fromDecimal((string) $value);
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
