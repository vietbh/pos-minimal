<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Order\Order;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\ValueObject\OrderNumber;
use Doctrine\ORM\EntityManagerInterface;

final class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
    }

    public function findById(int $id): ?Order
    {
        return $this->entityManager
            ->getRepository(Order::class)
            ->find($id);
    }

    public function findByOrderNumber(
        OrderNumber $orderNumber,
    ): ?Order {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.orderNumber = :orderNumber')
            ->setParameter('orderNumber', $orderNumber)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByOrderNumber(
        OrderNumber $orderNumber,
    ): bool {
        return $this->entityManager
                ->createQueryBuilder()
                ->select('1')
                ->from(Order::class, 'o')
                ->where('o.orderNumber = :orderNumber')
                ->setParameter('orderNumber', $orderNumber)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult() !== null;
    }
}
