<?php

declare(strict_types=1);

namespace App\Domain\Stock\Repository;

use App\Domain\Stock\StockMovement;
use App\Domain\Stock\Enum\StockMovementType;

interface StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void;

    /**
     * @return list<StockMovement>
     */
    public function findByProductId(int $productId): array;

    /**
     * @return array{items: list<StockMovement>, total: int}
     */
    public function findByProductIdPage(
        int $productId,
        ?StockMovementType $type,
        string $sort,
        int $page,
        int $perPage,
    ): array;

    /**
     * @return list<StockMovement>
     */
    public function findByOrderId(int $orderId): array;
}
