<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\Stock\StockMovement;
use Doctrine\ORM\EntityManagerInterface;

final class StockMovementRepository implements StockMovementRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(StockMovement $movement): void
    {
        $this->entityManager->persist($movement);
    }

    /**
     * @return list<StockMovement>
     */
    public function findByProductId(int $productId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('sm')
            ->from(StockMovement::class, 'sm')
            ->where('IDENTITY(sm.product) = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('sm.createdAt', 'ASC')
            ->addOrderBy('sm.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<StockMovement>
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('sm')
            ->from(StockMovement::class, 'sm')
            ->where('IDENTITY(sm.order) = :orderId')
            ->setParameter('orderId', $orderId)
            ->orderBy('sm.createdAt', 'ASC')
            ->addOrderBy('sm.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
