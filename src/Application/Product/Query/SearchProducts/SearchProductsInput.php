<?php

declare(strict_types=1);

namespace App\Application\Product\Query\SearchProducts;

final readonly class SearchProductsInput
{
    public function __construct(
        public string $query,
        public int $limit = 20,
    ) {
        if ($this->limit <= 0) {
            throw new \InvalidArgumentException(
                'Search limit must be greater than zero.',
            );
        }
    }
}
