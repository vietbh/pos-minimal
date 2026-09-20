<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Product\ProductCategory;
use App\Domain\Product\Repository\ProductCategoryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}
    public function save(ProductCategory $category): void { $this->entityManager->persist($category); }
    public function findById(int $id): ?ProductCategory { return $this->entityManager->find(ProductCategory::class, $id); }
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $qb = $this->entityManager->createQueryBuilder()->select('1')->from(ProductCategory::class, 'c')
            ->where('c.name = :name')->setParameter('name', trim($name))->setMaxResults(1);
        if ($excludeId !== null) $qb->andWhere('c.id != :id')->setParameter('id', $excludeId);
        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
    public function findAllOrdered(): array
    {
        return $this->entityManager->createQueryBuilder()->select('c')->from(ProductCategory::class, 'c')
            ->orderBy('c.name', 'ASC')->addOrderBy('c.id', 'ASC')->getQuery()->getResult();
    }

    public function findActiveOrdered(): array
    {
        return $this->entityManager->createQueryBuilder()->select('c')->from(ProductCategory::class, 'c')
            ->where('c.isActive = :active')->setParameter('active', true)->orderBy('c.name', 'ASC')->addOrderBy('c.id', 'ASC')
            ->getQuery()->getResult();
    }
}
