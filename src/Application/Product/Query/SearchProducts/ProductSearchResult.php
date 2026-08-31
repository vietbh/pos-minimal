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
        );
    }
}
