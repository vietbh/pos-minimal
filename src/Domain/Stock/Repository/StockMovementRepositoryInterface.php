<?php

declare(strict_types=1);

namespace App\Domain\Stock\Repository;

use App\Domain\Stock\StockMovement;

interface StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void;

    /**
     * @return list<StockMovement>
     */
    public function findByProductId(int $productId): array;

    /**
     * @return list<StockMovement>
     */
    public function findByOrderId(int $orderId): array;
}
