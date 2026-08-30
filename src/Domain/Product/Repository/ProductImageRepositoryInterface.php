<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

use App\Domain\Product\Enum\ImageStatus;
use App\Domain\Product\ProductImage;

interface ProductImageRepositoryInterface
{
    public function save(ProductImage $image): void;

    public function remove(ProductImage $image): void;

    public function findById(int $id): ?ProductImage;

    public function findByIdForUpdate(int $id): ?ProductImage;

    public function countByProductId(int $productId): int;

    public function findFirstReadyByProductId(int $productId, ?int $excludeId = null): ?ProductImage;
}
