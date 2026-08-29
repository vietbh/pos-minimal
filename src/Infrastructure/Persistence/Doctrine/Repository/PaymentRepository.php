<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Order\Payment;
use App\Domain\Order\Repository\PaymentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class PaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Payment $payment): void
    {
        $this->entityManager->persist($payment);
    }

    public function findById(int $id): ?Payment
    {
        return $this->entityManager
            ->getRepository(Payment::class)
            ->find($id);
    }

    /**
     * @return list<Payment>
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Payment::class, 'p')
            ->where('IDENTITY(p.order) = :orderId')
            ->setParameter('orderId', $orderId)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
