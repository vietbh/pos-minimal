<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Debt\DebtPayment;
use App\Domain\Debt\Repository\DebtPaymentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DebtPaymentRepository implements DebtPaymentRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(DebtPayment $payment): void
    {
        $this->entityManager->persist($payment);
    }

    public function findById(int $id): ?DebtPayment
    {
        return $this->entityManager
            ->getRepository(DebtPayment::class)
            ->find($id);
    }

    /**
     * @return list<DebtPayment>
     */
    public function findByDebtId(int $debtId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(DebtPayment::class, 'p')
            ->where('IDENTITY(p.debt) = :debtId')
            ->setParameter('debtId', $debtId)
            ->orderBy('p.createdAt', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
