<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repository;

use App\Domain\Audit\AuditLog;

interface AuditLogRepositoryInterface
{
    public function save(AuditLog $auditLog): void;

    /**
     * @return list<AuditLog>
     */
    public function findByEntity(
        string $entityType,
        string $entityId,
    ): array;

    /** @return list<AuditLog> */
    public function findRecent(int $limit = 200): array;
    /**
     * @return array{items:list<AuditLog>,total:int}
     */
    public function findRootAnalyticsPage(
        ?int $actorUserId,
        ?string $action,
        ?string $search,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $toExclusive,
        int $page,
        int $limit,
    ): array;

    /** @return list<string> */
    public function findRootAnalyticsActions(): array;

}
