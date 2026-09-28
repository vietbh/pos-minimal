<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Import\ImportBatch;
use App\Domain\Import\ImportRowState;
use App\Domain\Import\ImportRowStatus;
use App\Domain\Import\Repository\ImportRowStateRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final readonly class ImportRowStateRepository implements ImportRowStateRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}
    public function save(ImportRowState $state): void { $this->entityManager->persist($state); }
    public function findById(int $id): ?ImportRowState { return $this->entityManager->find(ImportRowState::class, $id); }
    public function find(ImportBatch $batch, int $rowNumber): ?ImportRowState
    {
        return $this->entityManager->createQueryBuilder()->select('s')->from(ImportRowState::class, 's')
            ->where('s.batch = :batch')->andWhere('s.rowNumber = :row')->setParameter('batch', $batch)->setParameter('row', $rowNumber)
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
    public function findByBatch(ImportBatch $batch): array
    {
        return $this->entityManager->createQueryBuilder()->select('s')->from(ImportRowState::class, 's')
            ->where('s.batch = :batch')->setParameter('batch', $batch)
            ->orderBy('s.rowNumber', 'ASC')->getQuery()->getResult();
    }

    public function findForUpdate(ImportBatch $batch, int $rowNumber): ?ImportRowState
    {
        $state = $this->find($batch, $rowNumber);
        if ($state === null) return null;
        $this->entityManager->lock($state, LockMode::PESSIMISTIC_WRITE);
        return $state;
    }

    public function findErrorRowNumbers(ImportBatch $batch): array
    {
        $rows = $this->entityManager->createQueryBuilder()->select('s.rowNumber AS rowNumber')
            ->from(ImportRowState::class, 's')->where('s.batch = :batch')->andWhere('s.status = :status')
            ->setParameter('batch', $batch)->setParameter('status', ImportRowStatus::FAILED)->getQuery()->getScalarResult();
        return array_map(static fn(array $row): int => (int) $row['rowNumber'], $rows);
    }
}
