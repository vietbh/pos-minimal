<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\ProductCategory;

interface ProductCategoryRepositoryInterface
{
    public function save(ProductCategory $category): void;
    public function findById(int $id): ?ProductCategory;
    public function existsByName(string $name, ?int $excludeId = null): bool;
    /** @return list<ProductCategory> */
    public function findActiveOrdered(): array;
    /** @return list<ProductCategory> */
    public function findAllOrdered(): array;
}
