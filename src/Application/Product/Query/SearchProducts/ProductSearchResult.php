<?php

declare(strict_types=1);

namespace App\Application\Product\Query\SearchProducts;

use App\Domain\Product\Product;

final readonly class ProductSearchResult
{
    public function __construct(
        public int $id,
        public ?string $sku,
        public string $name,
        public ?string $unit,
        public int $sellingPrice,
        public int $stockQuantity,
        public int $lowStockThreshold = 0,
        public ?int $categoryId = null,
        public ?string $categoryName = null,
        public ?int $imageId = null,
        public bool $isActive = true,
    ) {
    }

    public static function fromProduct(Product $product): self
    {
        $id = $product->getId();

        if ($id === null) {
            throw new \LogicException(
                'Cannot create a search result for an unsaved product.',
            );
        }

        return new self(
            id: $id,
            sku: $product->getSku()?->value(),
            name: $product->getName(),
            unit: $product->getUnit(),
            sellingPrice: $product->getSellingPrice()->minorUnits(),
            stockQuantity: $product->getStockQuantity(),
            lowStockThreshold: $product->getLowStockThreshold(),
            categoryId: $product->getCategory()?->getId(),
            categoryName: $product->getCategory()?->getName(),
            imageId: null,
            isActive: $product->isActive(),
        );
    }
}
