<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Security\Permission;
use App\Application\Security\PermissionMatrix;
use App\Application\Security\UserManagementPolicy;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class ChangeUserRoleService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
        private TransactionManagerInterface $transactionManager,
        private UserManagementPolicy $policy,
    ) {
    }

    public function change(
        User $actor,
        int $targetUserId,
        UserRole $role,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        if (!PermissionMatrix::allows($actor, Permission::USER_MANAGE)) {
            throw new \DomainException('You are not allowed to manage users.');
        }

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($actor, $targetUserId, $role, $ipAddress, $userAgent): void {
                $target = $this->userRepository->findByIdForUpdate($targetUserId);
                if (!$target instanceof User) {
                    throw new \RuntimeException('User not found.');
                }

                if (!$this->policy->canMutate($actor, $target) || !$this->policy->canCreateRole($actor, $role)) {
                    throw new \DomainException('You cannot manage this account or grant this role.');
                }

                $oldRoles = $target->getRoles();
                $target->setRoles([$role->value]);
                $newRoles = $target->getRoles();

                if ($oldRoles === $newRoles) {
                    return;
                }

                $this->userRepository->save($target);
                $this->auditLogRepository->save(new AuditLog(
                    action: 'USER_ROLE_CHANGED',
                    user: $actor,
                    entityType: 'User',
                    entityId: (string) $target->getId(),
                    oldValues: ['roles' => $oldRoles],
                    newValues: ['roles' => $newRoles],
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                ));

                $transaction->flush();
            },
        );
    }
}
