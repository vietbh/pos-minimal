<?php

declare(strict_types=1);
namespace App\Domain\Import\Repository;
use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportRowState;
interface ImportRowStateRepositoryInterface
{
    public function save(ImportRowState $state): void;
    public function findById(int $id): ?ImportRowState;
    public function find(ImportBatch $batch, int $rowNumber): ?ImportRowState;
    public function findForUpdate(ImportBatch $batch, int $rowNumber): ?ImportRowState;
    /** @return list<ImportRowState> */
    public function findByBatch(ImportBatch $batch): array;
    /** @return list<int> */
    public function findErrorRowNumbers(ImportBatch $batch): array;
}
