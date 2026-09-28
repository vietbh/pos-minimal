<?php

declare(strict_types=1);
namespace App\Domain\Import\Repository;
use App\Domain\Import\ImportBatch;
interface ImportBatchRepositoryInterface
{
    public function save(ImportBatch $batch): void;
    public function findById(int $id): ?ImportBatch;
    /** @return list<ImportBatch> */
    public function findRecent(int $limit = 50): array;
    /** @return array{items:list<ImportBatch>, total:int} */
    public function findPage(int $page, int $limit, ?int $requestedByUserId = null): array;
    /** @param list<int> $ids */
    public function findByIds(array $ids): array;
    public function remove(ImportBatch $batch): void;
}
