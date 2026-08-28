<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(int $id): ?User;

    public function findByUsername(string $username): ?User;

//    public function findByGoogleSubject(string $subject): ?User;
}
