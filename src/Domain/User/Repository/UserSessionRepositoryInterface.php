<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\UserSession;

interface UserSessionRepositoryInterface
{
    public function save(UserSession $session): void;

    public function findById(int $id): ?UserSession;

    public function findBySessionIdentifier(
        string $sessionIdentifier,
    ): ?UserSession;
}
