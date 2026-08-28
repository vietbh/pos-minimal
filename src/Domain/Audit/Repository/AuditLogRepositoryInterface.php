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
}
