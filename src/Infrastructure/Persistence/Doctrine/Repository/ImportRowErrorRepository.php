<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportRowError;
use App\Domain\Import\Repository\ImportRowErrorRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ImportRowErrorRepository implements ImportRowErrorRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}
    public function save(ImportRowError $error): void { $this->entityManager->persist($error); }
    public function deleteByBatch(ImportBatch $batch): void
    {
        $this->entityManager->createQueryBuilder()->delete(ImportRowError::class, 'e')
            ->where('e.batch = :batch')->setParameter('batch', $batch)->getQuery()->execute();
    }
    public function findByBatch(ImportBatch $batch, int $limit = 1000): array
    {
        $limit = max(1, min(10000, $limit));
        return $this->entityManager->createQueryBuilder()->select('e')->from(ImportRowError::class, 'e')
            ->where('e.batch = :batch')->setParameter('batch', $batch)
            ->orderBy('e.rowNumber', 'ASC')->addOrderBy('e.id', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
