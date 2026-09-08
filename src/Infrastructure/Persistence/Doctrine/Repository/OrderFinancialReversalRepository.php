<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Order\OrderFinancialReversal;
use App\Domain\Order\Repository\OrderFinancialReversalRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class OrderFinancialReversalRepository implements OrderFinancialReversalRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function save(OrderFinancialReversal $reversal): void
    {
        $this->entityManager->persist($reversal);
    }

    public function findByOrderId(int $orderId): ?OrderFinancialReversal
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(OrderFinancialReversal::class, 'r')
            ->where('IDENTITY(r.order) = :orderId')
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
