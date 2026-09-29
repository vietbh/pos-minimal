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
    public function findRecent(int $limit = 200): array
    {
        $limit = min(500, max(1, $limit));

        return $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRootAnalyticsActions(): array
    {
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('DISTINCT a.action AS action')
            ->from(AuditLog::class, 'a')
            ->innerJoin('a.user', 'u')
            ->where('u.roles NOT LIKE :rootPattern')
            ->setParameter('rootPattern', '%"ROLE_ROOT"%')
            ->orderBy('a.action', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['action'],
            $rows,
        ));
    }

    public function findRootAnalyticsPage(
        ?int $actorUserId,
        ?string $action,
        ?string $search,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $toExclusive,
        int $page,
        int $limit,
    ): array {
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager
            ->createQueryBuilder()
            ->from(AuditLog::class, 'a')
            ->innerJoin('a.user', 'u')
            ->andWhere('u.roles NOT LIKE :rootPattern')
            ->setParameter('rootPattern', '%"ROLE_ROOT"%');

        if ($actorUserId !== null) {
            $qb->andWhere('u.id = :actorUserId')
                ->setParameter('actorUserId', $actorUserId);
        }

        if ($action !== null && $action !== '') {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere(
                '(LOWER(u.username) LIKE LOWER(:search)
                OR LOWER(a.action) LIKE LOWER(:search)
                OR LOWER(a.entityType) LIKE LOWER(:search)
                OR LOWER(a.entityId) LIKE LOWER(:search))'
            )->setParameter('search', '%' . trim($search) . '%');
        }

        if ($from !== null) {
            $qb->andWhere('a.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($toExclusive !== null) {
            $qb->andWhere('a.createdAt < :toExclusive')
                ->setParameter('toExclusive', $toExclusive);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->select('a', 'u')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => array_values(array_filter(
                $items,
                static fn (mixed $item): bool => $item instanceof AuditLog,
            )),
            'total' => $total,
        ];
    }

}

