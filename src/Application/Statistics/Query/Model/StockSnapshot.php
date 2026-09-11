<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query\Model;
final readonly class StockSnapshot
{
    public function __construct(public int $totalProducts, public int $activeProducts, public int $lowStockProducts, public int $outOfStockProducts, public int $totalStockQuantity) {}
}
