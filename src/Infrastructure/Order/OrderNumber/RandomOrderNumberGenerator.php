<?php

declare(strict_types=1);

namespace App\Infrastructure\Order\OrderNumber;

use App\Application\Order\OrderNumberGeneratorInterface;
use App\Domain\Order\ValueObject\OrderNumber;

final class RandomOrderNumberGenerator implements OrderNumberGeneratorInterface
{
    private const PREFIX = 'ORD-';
    private const RANDOM_BYTES = 8;

    public function generate(): OrderNumber
    {
        return new OrderNumber(
            self::PREFIX . strtoupper(
                bin2hex(random_bytes(self::RANDOM_BYTES)),
            ),
        );
    }
}
