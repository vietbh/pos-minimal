<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\UserSession;
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
}
