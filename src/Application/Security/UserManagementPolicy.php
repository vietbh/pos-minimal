<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;

/**
 * Centralizes the user hierarchy rules.
 *
 * ROOT is a bootstrap/master account. It is deliberately not a manageable
 * target through the normal User Management UI, even for another ROOT account.
 * ADMIN is the next tier: ADMIN may see peer ADMIN accounts but may not mutate
 * or switch to them. ROOT may manage ADMIN and USER accounts.
 */
final class UserManagementPolicy
{
    public function canManage(User $actor): bool
    {
        return $actor->isActive()
            && ($actor->hasRole(UserRole::ROOT) || $actor->hasRole(UserRole::ADMIN));
    }

    public function canView(User $actor, User $target): bool
    {
        if (!$this->canManage($actor)) {
            return false;
        }

        // ROOT is intentionally invisible and non-addressable from User Management.
        if ($target->hasRole(UserRole::ROOT)) {
            return false;
        }

        if ($actor->hasRole(UserRole::ROOT)) {
            return $target->hasRole(UserRole::ADMIN) || $target->hasRole(UserRole::USER);
        }

        // ADMIN may see every non-root account, including other ADMIN accounts,
        // but mutation/switch policy below still prevents acting on ADMIN peers.
        return $actor->hasRole(UserRole::ADMIN)
            && ($target->hasRole(UserRole::ADMIN) || $target->hasRole(UserRole::USER));
    }

    public function canMutate(User $actor, User $target): bool
    {
        if (!$this->canView($actor, $target) || $target->getId() === $actor->getId()) {
            return false;
        }

        if ($actor->hasRole(UserRole::ROOT)) {
            return $target->hasRole(UserRole::ADMIN) || $target->hasRole(UserRole::USER);
        }

        return $actor->hasRole(UserRole::ADMIN)
            && $target->hasRole(UserRole::USER)
            && !$target->hasRole(UserRole::ADMIN);
    }

    public function canCreateRole(User $actor, UserRole $role): bool
    {
        if (!$this->canManage($actor)) {
            return false;
        }

        // ROOT can never be created from the UI/application user-management flow.
        if ($role === UserRole::ROOT) {
            return false;
        }

        if ($actor->hasRole(UserRole::ROOT)) {
            return $role === UserRole::USER || $role === UserRole::ADMIN;
        }

        return $actor->hasRole(UserRole::ADMIN) && $role === UserRole::USER;
    }

    public function canSwitchTo(User $actor, User $target): bool
    {
        if (!$this->canManage($actor) || !$target->isActive()) {
            return false;
        }

        // Never expose or impersonate the root bootstrap identity.
        if ($target->hasRole(UserRole::ROOT)) {
            return false;
        }

        if ($actor->hasRole(UserRole::ROOT)) {
            return $target->hasRole(UserRole::USER) || $target->hasRole(UserRole::ADMIN);
        }

        return $actor->hasRole(UserRole::ADMIN)
            && $target->hasRole(UserRole::USER)
            && !$target->hasRole(UserRole::ADMIN);
    }

    /** @return list<UserRole> */
    public function visibleRoles(User $actor): array
    {
        if ($actor->hasRole(UserRole::ROOT)) {
            return [UserRole::USER, UserRole::ADMIN];
        }

        if ($actor->hasRole(UserRole::ADMIN)) {
            return [UserRole::USER, UserRole::ADMIN];
        }

        return [];
    }

    /** @return list<UserRole> */
    public function manageableRoles(User $actor): array
    {
        return $this->visibleRoles($actor);
    }
}
