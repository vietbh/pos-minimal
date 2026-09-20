<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Payment\PaymentReference;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class PaymentReferenceRepository implements PaymentReferenceRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function save(PaymentReference $reference): void
    {
        $this->entityManager->persist($reference);
    }

    public function findByReference(string $reference): ?PaymentReference
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(PaymentReference::class, 'r')
            ->where('r.reference = :reference')->setParameter('reference', strtoupper(trim($reference)))
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function findByReferenceForUpdate(string $reference): ?PaymentReference
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(PaymentReference::class, 'r')
            ->where('r.reference = :reference')->setParameter('reference', strtoupper(trim($reference)))
            ->setMaxResults(1)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }

    public function findLatestByOrderId(int $orderId): ?PaymentReference
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(PaymentReference::class, 'r')
            ->where('IDENTITY(r.order) = :orderId')->setParameter('orderId', $orderId)
            ->orderBy('r.id', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function findLatestByOrderIdForUpdate(int $orderId): ?PaymentReference
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(PaymentReference::class, 'r')
            ->where('IDENTITY(r.order) = :orderId')->setParameter('orderId', $orderId)
            ->orderBy('r.id', 'DESC')->setMaxResults(1)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }
    public function findLatestPendingBySession(int $sessionId): ?PaymentReference
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(PaymentReference::class, 'r')
            ->where('IDENTITY(r.checkoutPaymentSession) = :sessionId')
            ->andWhere('r.status = :status')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('status', \App\Domain\Payment\Enum\PaymentReferenceStatus::PENDING)
            ->orderBy('r.id', 'DESC')->setMaxResults(1)->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestPendingBySessionForUpdate(int $sessionId): ?PaymentReference
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(PaymentReference::class, 'r')
            ->where('IDENTITY(r.checkoutPaymentSession) = :sessionId')
            ->andWhere('r.status = :status')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('status', \App\Domain\Payment\Enum\PaymentReferenceStatus::PENDING)
            ->orderBy('r.id', 'DESC')->setMaxResults(1)->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }

}

