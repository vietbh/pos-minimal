<?php

declare(strict_types=1);
namespace App\Application\Product\Query;
use App\Application\Product\Query\SearchProducts\ProductSearchResult;
use App\Application\Product\Query\ProductQueryRepositoryInterface;
final readonly class ProductCatalogHandler
{
    public function __construct(private ProductQueryRepositoryInterface $repository) {}
    public function __invoke(ProductCatalogInput $input): ProductCatalogResult { return $this->repository->catalog($input); }
}
