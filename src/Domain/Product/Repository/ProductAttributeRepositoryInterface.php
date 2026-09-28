<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\Product;
use App\Domain\Product\ProductAttribute;

interface ProductAttributeRepositoryInterface
{
    /** @return list<ProductAttribute> */
    public function findByProduct(Product $product): array;

    public function save(ProductAttribute $attribute): void;

    public function deleteByProduct(Product $product): void;
}
