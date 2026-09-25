<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

use App\Domain\SalesPoint\Enum\SalesPointType;

final readonly class OrderSalesPointResult
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public SalesPointType $type,
        public ?string $groupName,
    ) {
    }
}
