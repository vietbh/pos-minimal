<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\User;
use App\Domain\User\UserSession;
use App\Domain\User\Enum\SessionStatus;

interface UserSessionRepositoryInterface
{
    public function save(UserSession $session): void;

    public function findById(int $id): ?UserSession;

    public function findBySessionIdentifier(
        string $sessionIdentifier,
    ): ?UserSession;

    /** @return list<UserSession> */
    public function findByUser(User $user, int $limit = 20): array;

    /** @return list<UserSession> */
    public function findByUserPaginated(User $user, int $page = 1, int $limit = 3): array;

    public function countByUser(User $user): int;

    /** @return list<UserSession> */
    public function findActiveByUserForUpdate(User $user): array;

    /** @return list<UserSession> */
    public function findRecentForAnalytics(
        int $limit = 500,
        ?SessionStatus $status = null,
    ): array;

    /** @return list<array{userId:int,requestCount:int,sessionCount:int,activeSessionCount:int}> */
    public function requestStatsByUser(): array;
}
