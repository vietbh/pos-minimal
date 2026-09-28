<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Product\Product;
use App\Domain\Product\ProductAttribute;
use App\Domain\Product\Repository\ProductAttributeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ProductAttributeRepository implements ProductAttributeRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function findByProduct(Product $product): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(ProductAttribute::class, 'a')
            ->where('a.product = :product')
            ->setParameter('product', $product)
            ->orderBy('a.sortOrder', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(ProductAttribute $attribute): void { $this->entityManager->persist($attribute); }

    public function deleteByProduct(Product $product): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(ProductAttribute::class, 'a')
            ->where('a.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->execute();
    }
}
