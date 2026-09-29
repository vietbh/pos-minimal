<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserSession;
use App\Domain\User\Enum\SessionStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class UserSessionRepository implements UserSessionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(UserSession $session): void
    {
        $this->entityManager->persist($session);
    }

    public function findById(int $id): ?UserSession
    {
        return $this->entityManager
            ->getRepository(UserSession::class)
            ->find($id);
    }

    public function findBySessionIdentifier(
        string $sessionIdentifier,
    ): ?UserSession {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(UserSession::class, 's')
            ->where('s.sessionIdentifier = :sessionIdentifier')
            ->setParameter(
                'sessionIdentifier',
                $sessionIdentifier,
            )
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUser(User $user, int $limit = 20): array
    {
        $limit = min(50, max(1, $limit));

        return $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(UserSession::class, 's')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByUserPaginated(User $user, int $page = 1, int $limit = 3): array
    {
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));
        $offset = ($page - 1) * $limit;

        return $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(UserSession::class, 's')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(UserSession::class, 's')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveByUserForUpdate(User $user): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(UserSession::class, 's')
            ->where('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', SessionStatus::ACTIVE)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }
    public function findRecentForAnalytics(
        int $limit = 500,
        ?SessionStatus $status = null,
    ): array {
        $limit = min(5000, max(1, $limit));

        $qb = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(UserSession::class, 's');

        if ($status !== null) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $status);
        }

        return $qb
            ->orderBy('s.lastActivityAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function requestStatsByUser(): array
    {
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('IDENTITY(s.user) AS userId')
            ->addSelect('SUM(s.requestCount) AS requestCount')
            ->addSelect('COUNT(s.id) AS sessionCount')
            ->addSelect("SUM(CASE WHEN s.status = :activeStatus THEN 1 ELSE 0 END) AS activeSessionCount")
            ->from(UserSession::class, 's')
            ->setParameter('activeStatus', SessionStatus::ACTIVE)
            ->groupBy('s.user')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'userId' => (int) $row['userId'],
            'requestCount' => (int) $row['requestCount'],
            'sessionCount' => (int) $row['sessionCount'],
            'activeSessionCount' => (int) $row['activeSessionCount'],
        ], $rows);
    }

}

