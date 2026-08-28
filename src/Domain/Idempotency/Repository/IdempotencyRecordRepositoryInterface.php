<?php

declare(strict_types=1);

namespace App\Domain\Idempotency\Repository;

use App\Domain\Idempotency\IdempotencyRecord;

interface IdempotencyRecordRepositoryInterface
{
    public function save(IdempotencyRecord $record): void;

    public function findByKey(
        int $userId,
        string $idempotencyKey,
    ): ?IdempotencyRecord;
}
