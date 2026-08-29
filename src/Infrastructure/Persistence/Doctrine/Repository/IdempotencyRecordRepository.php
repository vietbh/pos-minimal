<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Idempotency\IdempotencyRecord;
use App\Domain\Idempotency\Repository\IdempotencyRecordRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class IdempotencyRecordRepository implements IdempotencyRecordRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(IdempotencyRecord $record): void
    {
        $this->entityManager->persist($record);
    }

    public function findByKey(
        int $userId,
        string $idempotencyKey,
    ): ?IdempotencyRecord {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('i')
            ->from(IdempotencyRecord::class, 'i')
            ->where('IDENTITY(i.user) = :userId')
            ->andWhere('i.idempotencyKey = :idempotencyKey')
            ->setParameter('userId', $userId)
            ->setParameter('idempotencyKey', $idempotencyKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
