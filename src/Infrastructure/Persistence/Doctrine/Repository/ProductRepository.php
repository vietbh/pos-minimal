<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Product\Product;
use App\Domain\Product\Repository\ProductRepositoryInterface;
use App\Domain\Product\ValueObject\Sku;
use Doctrine\ORM\EntityManagerInterface;

final class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
    }

    public function findById(int $id): ?Product
    {
        return $this->entityManager
            ->getRepository(Product::class)
            ->find($id);
    }

    public function findBySku(Sku $sku): ?Product
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.sku = :sku')
            ->setParameter('sku', $sku)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsBySku(
        Sku $sku,
        ?int $excludeId = null,
    ): bool {
        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->select('1')
            ->from(Product::class, 'p')
            ->where('p.sku = :sku')
            ->setParameter('sku', $sku)
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $queryBuilder
                ->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $queryBuilder
                ->getQuery()
                ->getOneOrNullResult() !== null;
    }
}
