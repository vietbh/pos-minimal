<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Product\Enum\ImageStatus;
use App\Domain\Product\ProductImage;
use App\Domain\Product\Repository\ProductImageRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class ProductImageRepository implements ProductImageRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(ProductImage $image): void
    {
        $this->entityManager->persist($image);
    }

    public function remove(ProductImage $image): void
    {
        $this->entityManager->remove($image);
    }

    public function findById(int $id): ?ProductImage
    {
        return $this->entityManager->find(ProductImage::class, $id);
    }

    public function findByIdForUpdate(int $id): ?ProductImage
    {
        return $this->entityManager->find(
            ProductImage::class,
            $id,
            LockMode::PESSIMISTIC_WRITE,
        );
    }

    public function countByProductId(int $productId): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(ProductImage::class, 'i')
            ->andWhere('IDENTITY(i.product) = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findFirstReadyByProductId(int $productId, ?int $excludeId = null): ?ProductImage
    {
        $qb = $this->entityManager
            ->createQueryBuilder()
            ->select('i')
            ->from(ProductImage::class, 'i')
            ->andWhere('IDENTITY(i.product) = :productId')
            ->andWhere('i.status = :status')
            ->setParameter('productId', $productId)
            ->setParameter('status', ImageStatus::READY)
            ->orderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere('i.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
