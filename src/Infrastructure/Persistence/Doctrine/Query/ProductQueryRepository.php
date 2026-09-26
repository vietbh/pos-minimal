<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Product\Query\ProductQueryRepositoryInterface;
use App\Application\Product\Query\ProductCatalogInput;
use App\Application\Product\Query\ProductCatalogResult;
use App\Domain\Product\Enum\ImageStatus;
use App\Domain\Product\ProductCategory;
use App\Domain\Product\ProductImage;
use App\Application\Product\Query\SearchProducts\ProductSearchResult;
use App\Domain\Product\Product;
use Doctrine\ORM\EntityManagerInterface;

final class ProductQueryRepository implements ProductQueryRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<ProductSearchResult>
     */
    public function search(string $query, int $limit): array
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException(
                'Search limit must be greater than zero.',
            );
        }

        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $pattern = '%'.$query.'%';

        /** @var list<Product> $products */
        $products = $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.isActive = :active')
            ->andWhere(
                '(p.name LIKE :query OR p.sku LIKE :query)',
            )
            ->setParameter('active', true)
            ->setParameter('query', $pattern)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (Product $product): ProductSearchResult =>
                ProductSearchResult::fromProduct($product),
            $products,
        );
    }

    public function catalog(ProductCatalogInput $input): ProductCatalogResult
    {
        $page = max(1, $input->page);
        $limit = min(50, max(1, $input->limit));
        $query = trim($input->query);
        $sort = in_array($input->sort, ['name_asc', 'name_desc'], true) ? $input->sort : 'name_asc';

        $base = $this->entityManager->createQueryBuilder()
            ->from(Product::class, 'p')
            ->leftJoin('p.category', 'c')
            ->where('p.isActive = :active')
            ->setParameter('active', true);
        if ($query !== '') {
            $base->andWhere('(p.name LIKE :q OR p.sku LIKE :q)')->setParameter('q', '%'.$query.'%');
        }
        if ($input->categoryId !== null && $input->categoryId > 0) {
            $base->andWhere('c.id = :categoryId')->setParameter('categoryId', $input->categoryId);
        }
        if ($input->stockFilter === 'low') {
            $base->andWhere('p.stockQuantity > 0')
                ->andWhere('p.stockQuantity <= p.lowStockThreshold');
        } elseif ($input->stockFilter === 'out') {
            $base->andWhere('p.stockQuantity = 0');
        } elseif ($input->stockFilter === 'ok') {
            $base->andWhere('p.stockQuantity > p.lowStockThreshold');
        }
        $total = (int) (clone $base)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $direction = $sort === 'name_desc' ? 'DESC' : 'ASC';
        $products = $base->select('p', 'c')
            ->orderBy('p.name', $direction)->addOrderBy('p.id', $direction)
            ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();
        $items = array_map(static fn(Product $product): ProductSearchResult => ProductSearchResult::fromProduct($product), $products);
        $totalPages = max(1, (int) ceil($total / $limit));
        return new ProductCatalogResult($items, $page, $limit, $total, $totalPages);
    }

    public function findActiveBySku(string $sku): ?ProductSearchResult
    {
        $sku = trim($sku);

        if ($sku === '') {
            return null;
        }

        /** @var Product|null $product */
        $product = $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.isActive = :active')
            ->andWhere('p.sku = :sku')
            ->setParameter('active', true)
            ->setParameter('sku', $sku)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $product === null
            ? null
            : ProductSearchResult::fromProduct($product);
    }
}
