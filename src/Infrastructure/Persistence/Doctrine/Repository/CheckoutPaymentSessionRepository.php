<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Payment\CheckoutPaymentSession;
use App\Domain\Payment\Repository\CheckoutPaymentSessionRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CheckoutPaymentSessionRepository implements CheckoutPaymentSessionRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}
    public function save(CheckoutPaymentSession $session): void { $this->entityManager->persist($session); }
    public function findById(int $id): ?CheckoutPaymentSession { return $this->entityManager->find(CheckoutPaymentSession::class, $id); }
    public function findByIdForUpdate(int $id): ?CheckoutPaymentSession { return $this->entityManager->find(CheckoutPaymentSession::class, $id, LockMode::PESSIMISTIC_WRITE); }
    public function findActiveByKeyForUpdate(string $activeKey): ?CheckoutPaymentSession {
        return $this->entityManager->createQueryBuilder()->select('s')->from(CheckoutPaymentSession::class, 's')
            ->where('s.activeKey = :key')->andWhere('s.status = :status')
            ->setParameter('key', hash('sha256', trim($activeKey)))
            ->setParameter('status', \App\Domain\Payment\Enum\CheckoutPaymentSessionStatus::WAITING_FOR_BANK_PAYMENT)
            ->setMaxResults(1)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }
}
