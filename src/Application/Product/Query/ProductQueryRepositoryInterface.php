<?php

declare(strict_types=1);

namespace App\Application\Product\Query;

use App\Application\Product\Query\SearchProducts\ProductSearchResult;

interface ProductQueryRepositoryInterface
{
    /**
     * @return list<ProductSearchResult>
     */
    public function search(string $query, int $limit): array;

    public function findActiveBySku(string $sku): ?ProductSearchResult;
}
