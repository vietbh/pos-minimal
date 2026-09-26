<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\Stock\Enum\StockMovementType;
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
     * @return array{items: list<StockMovement>, total: int}
     */
    public function findByProductIdPage(
        int $productId,
        ?StockMovementType $type,
        string $sort,
        int $page,
        int $perPage,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $qb = $this->entityManager
            ->createQueryBuilder()
            ->from(StockMovement::class, 'sm')
            ->where('IDENTITY(sm.product) = :productId')
            ->setParameter('productId', $productId);

        if ($type !== null) {
            $qb->andWhere('sm.type = :type')
                ->setParameter('type', $type);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(sm.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
        $items = $qb
            ->select('sm')
            ->orderBy('sm.createdAt', $direction)
            ->addOrderBy('sm.id', $direction)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
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
