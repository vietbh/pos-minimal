<?php

declare(strict_types=1);

namespace App\Domain\Order\Repository;

use App\Domain\Order\Order;
use App\Domain\Order\ValueObject\OrderNumber;

interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    public function findById(int $id): ?Order;

    public function findByOrderNumber(
        OrderNumber $orderNumber,
    ): ?Order;

    public function existsByOrderNumber(
        OrderNumber $orderNumber,
    ): bool;
}
