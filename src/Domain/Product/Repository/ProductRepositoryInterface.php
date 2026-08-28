<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\Product;
use App\Domain\Product\ValueObject\Sku;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(int $id): ?Product;

    public function findBySku(Sku $sku): ?Product;

    public function existsBySku(Sku $sku, ?int $excludeId = null): bool;
}
