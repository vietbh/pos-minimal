<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Debt\Debt;
use App\Domain\Debt\Repository\DebtRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DebtRepository implements DebtRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Debt $debt): void
    {
        $this->entityManager->persist($debt);
    }

    public function findById(int $id): ?Debt
    {
        return $this->entityManager
            ->getRepository(Debt::class)
            ->find($id);
    }

    public function findByOrderId(int $orderId): ?Debt
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->where('IDENTITY(d.order) = :orderId')
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Debt>
     */
    public function findByCustomerId(int $customerId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->where('IDENTITY(d.customer) = :customerId')
            ->setParameter('customerId', $customerId)
            ->orderBy('d.createdAt', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
