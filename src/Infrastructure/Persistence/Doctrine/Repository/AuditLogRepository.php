<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(AuditLog $auditLog): void
    {
        $this->entityManager->persist($auditLog);
    }

    /**
     * @return list<AuditLog>
     */
    public function findByEntity(
        string $entityType,
        string $entityId,
    ): array {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->where('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
