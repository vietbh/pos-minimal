<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Voter;

use App\Application\Security\Permission;
use App\Application\Security\PermissionMatrix;
use App\Domain\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PermissionVoter extends Voter
{
    protected function supports(
        string $attribute,
        mixed $subject,
    ): bool {
        return Permission::tryFrom($attribute) !== null;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $permission = Permission::tryFrom($attribute);

        if ($permission === null) {
            return false;
        }

        return PermissionMatrix::allows(
            $user,
            $permission,
        );
    }
}
