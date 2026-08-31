<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Product\Query\ProductQueryRepositoryInterface;
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
