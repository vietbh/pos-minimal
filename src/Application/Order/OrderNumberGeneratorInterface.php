<?php

declare(strict_types=1);

namespace App\Application\Order;

use App\Domain\Order\ValueObject\OrderNumber;

interface OrderNumberGeneratorInterface
{
    public function generate(): OrderNumber;
}
