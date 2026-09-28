<?php

declare(strict_types=1);
namespace App\Domain\Import\Repository;
use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportRowError;
interface ImportRowErrorRepositoryInterface
{
    public function save(ImportRowError $error): void;
    public function deleteByBatch(ImportBatch $batch): void;
    /** @return list<ImportRowError> */
    public function findByBatch(ImportBatch $batch, int $limit = 1000): array;
}
