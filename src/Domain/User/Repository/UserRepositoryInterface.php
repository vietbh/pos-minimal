<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(int $id): ?User;

    /** Load a user with a database write lock. Call only inside a transaction. */
    public function findByIdForUpdate(int $id): ?User;

    public function findByUsername(string $username): ?User;

    public function findRoot(): ?User;

    /** @return list<User> */
    public function findAllOrderedByUsername(): array;

    /**
     * @return array{items:list<User>, total:int}
     */
    public function searchPage(
        ?string $search,
        ?bool $active,
        ?UserRole $role,
        int $page,
        int $limit,
        /** @param list<UserRole> $visibleRoles */
        array $visibleRoles,
    ): array;
}
