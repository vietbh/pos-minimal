<?php

declare(strict_types=1);

namespace App\Application\Product\Query\SearchProducts;

use App\Application\Product\Query\ProductQueryRepositoryInterface;

final class SearchProductsHandler
{
    public function __construct(
        private readonly ProductQueryRepositoryInterface $productQueryRepository,
    ) {
    }

    /**
     * @return list<ProductSearchResult>
     */
    public function __invoke(SearchProductsInput $input): array
    {
        $query = trim($input->query);

        if ($query === '') {
            return [];
        }

        return $this->productQueryRepository->search(
            $query,
            $input->limit,
        );
    }
}
