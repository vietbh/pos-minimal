<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Import\ImportBatch;
use App\Domain\Import\Repository\ImportBatchRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ImportBatchRepository implements ImportBatchRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}
    public function save(ImportBatch $batch): void { $this->entityManager->persist($batch); }
    public function findById(int $id): ?ImportBatch { return $this->entityManager->find(ImportBatch::class, $id); }
    public function findRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->entityManager->createQueryBuilder()
            ->select('b')->from(ImportBatch::class, 'b')
            ->orderBy('b.createdAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->setMaxResults($limit)->getQuery()->getResult();
    }

    public function findPage(int $page, int $limit, ?int $requestedByUserId = null): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $base = $this->entityManager->createQueryBuilder()->from(ImportBatch::class, 'b');
        if ($requestedByUserId !== null) {
            $base->andWhere('IDENTITY(b.requestedBy) = :userId')->setParameter('userId', $requestedByUserId);
        }
        $total = (int) (clone $base)->select('COUNT(b.id)')->getQuery()->getSingleScalarResult();
        $items = $base->select('b')
            ->orderBy('b.createdAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)
            ->getQuery()->getResult();
        return ['items' => $items, 'total' => $total];
    }

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        return $this->entityManager->createQueryBuilder()
            ->select('b')->from(ImportBatch::class, 'b')
            ->where('b.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->getResult();
    }

    public function remove(ImportBatch $batch): void { $this->entityManager->remove($batch); }
}
