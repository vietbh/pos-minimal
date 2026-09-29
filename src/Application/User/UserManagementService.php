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
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserManagementService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserSessionRepositoryInterface $userSessionRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
        private TransactionManagerInterface $transactionManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserManagementPolicy $policy,
    ) {
    }

    public function create(
        User $actor,
        string $username,
        string $password,
        UserRole $role,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): User {
        $this->assertCanManage($actor);
        if (!$this->policy->canCreateRole($actor, $role)) {
            throw new \DomainException('You cannot create an account at this role level.');
        }
        $username = trim($username);
        $this->assertPassword($password);

        try {
            return $this->transactionManager->run(
                function (TransactionContextInterface $transaction) use ($actor, $username, $password, $role, $ipAddress, $userAgent): User {
                    if ($this->userRepository->findByUsername($username) instanceof User) {
                        throw new \DomainException('A user with this username already exists.');
                    }

                    $user = new User($username);
                    $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
                    $user->setRoles([$role->value]);
                    $this->userRepository->save($user);
                    $transaction->flush();

                    $this->auditLogRepository->save(new AuditLog(
                        action: 'USER_CREATED',
                        user: $actor,
                        entityType: 'User',
                        entityId: (string) $user->getId(),
                        newValues: [
                            'username' => $user->getUsername(),
                            'roles' => $user->getRoles(),
                            'isActive' => $user->isActive(),
                            'passwordSet' => true,
                        ],
                        ipAddress: $ipAddress,
                        userAgent: $userAgent,
                    ));
                    $transaction->flush();

                    return $user;
                },
            );
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('A user with this username already exists.');
        }
    }

    public function update(
        User $actor,
        int $targetUserId,
        string $username,
        UserRole $role,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->assertCanManage($actor);
        $username = trim($username);

        try {
            $this->transactionManager->run(
                function (TransactionContextInterface $transaction) use ($actor, $targetUserId, $username, $role, $ipAddress, $userAgent): void {
                    $target = $this->requireTargetForUpdate($targetUserId);
                    if (!$this->policy->canMutate($actor, $target)) {
                        throw new \DomainException('You cannot manage this account.');
                    }
                    if (!$this->policy->canCreateRole($actor, $role)) {
                        throw new \DomainException('You cannot grant this role.');
                    }
                    $existing = $this->userRepository->findByUsername($username);
                    if ($existing instanceof User && $existing->getId() !== $target->getId()) {
                        throw new \DomainException('A user with this username already exists.');
                    }

                    if ($target->getId() === $actor->getId() && !$target->hasRole($role)) {
                        throw new \DomainException('You cannot change your own role.');
                    }

                    $oldValues = [
                        'username' => $target->getUsername(),
                        'roles' => $target->getRoles(),
                    ];
                    $newRoles = array_values(array_unique([$role->value, UserRole::USER->value]));
                    $newValues = [
                        'username' => $username,
                        'roles' => $newRoles,
                    ];

                    if ($oldValues === $newValues) {
                        return;
                    }

                    $target->changeUsername($username);
                    $target->setRoles([$role->value]);
                    $this->userRepository->save($target);
                    $this->auditLogRepository->save(new AuditLog(
                        action: 'USER_UPDATED',
                        user: $actor,
                        entityType: 'User',
                        entityId: (string) $target->getId(),
                        oldValues: $oldValues,
                        newValues: $newValues,
                        ipAddress: $ipAddress,
                        userAgent: $userAgent,
                    ));
                    $transaction->flush();
                },
            );
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('A user with this username already exists.');
        }
    }

    public function setActive(
        User $actor,
        int $targetUserId,
        bool $active,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->assertCanManage($actor);

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($actor, $targetUserId, $active, $ipAddress, $userAgent): void {
                $target = $this->requireTargetForUpdate($targetUserId);
                if (!$this->policy->canMutate($actor, $target)) {
                    throw new \DomainException('You cannot manage this account.');
                }
                $old = $target->isActive();
                if ($old === $active) {
                    return;
                }

                if ($active) {
                    $target->activate();
                    $action = 'USER_ACTIVATED';
                } else {
                    $target->deactivate();
                    foreach ($this->userSessionRepository->findActiveByUserForUpdate($target) as $session) {
                        $session->revoke();
                        $this->userSessionRepository->save($session);
                    }
                    $action = 'USER_DEACTIVATED';
                }

                $this->userRepository->save($target);
                $this->auditLogRepository->save(new AuditLog(
                    action: $action,
                    user: $actor,
                    entityType: 'User',
                    entityId: (string) $target->getId(),
                    oldValues: ['isActive' => $old],
                    newValues: ['isActive' => $target->isActive()],
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                ));
                $transaction->flush();
            },
        );
    }

    public function resetPassword(
        User $actor,
        int $targetUserId,
        string $newPassword,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->assertCanManage($actor);
        $this->assertPassword($newPassword);

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($actor, $targetUserId, $newPassword, $ipAddress, $userAgent): void {
                $target = $this->requireTargetForUpdate($targetUserId);
                if (!$this->policy->canMutate($actor, $target)) {
                    throw new \DomainException('You cannot manage this account.');
                }
                $target->setPasswordHash($this->passwordHasher->hashPassword($target, $newPassword));
                $this->userRepository->save($target);
                $this->auditLogRepository->save(new AuditLog(
                    action: 'USER_PASSWORD_RESET',
                    user: $actor,
                    entityType: 'User',
                    entityId: (string) $target->getId(),
                    newValues: ['passwordReset' => true],
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                ));
                $transaction->flush();
            },
        );
    }

    public function revokeSession(
        User $actor,
        int $targetUserId,
        int $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->assertCanManage($actor);

        $this->transactionManager->run(function (TransactionContextInterface $transaction) use ($actor, $targetUserId, $sessionId, $ipAddress, $userAgent): void {
            $target = $this->requireTargetForUpdate($targetUserId);
            if (!$this->policy->canMutate($actor, $target)) {
                throw new \DomainException('You cannot manage this account.');
            }

            $sessions = $this->userSessionRepository->findByUser($target, 50);
            $session = null;
            foreach ($sessions as $candidate) {
                if ($candidate->getId() === $sessionId) {
                    $session = $candidate;
                    break;
                }
            }
            if ($session === null) {
                throw new \RuntimeException('Session not found.');
            }
            if ($session->isActive()) {
                $session->revoke();

                // Symfony's signed remember-me token can be invalidated by
                // changing a configured signature property. The firewall is
                // configured to sign both password and updatedAt, so a remote
                // session revoke invalidates remember-me cookies for this
                // account as well. Existing full-authentication sessions are
                // still enforced by UserSessionActivitySubscriber.
                $target->updateTimestamp();

                $this->userSessionRepository->save($session);
                $this->auditLogRepository->save(new AuditLog(
                    action: 'USER_SESSION_REVOKED',
                    user: $actor,
                    session: $session,
                    entityType: 'User',
                    entityId: (string) $target->getId(),
                    newValues: ['sessionId' => $sessionId],
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                ));
                $transaction->flush();
            }
        });
    }

    private function requireTargetForUpdate(int $id): User
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid user id.');
        }

        $user = $this->userRepository->findByIdForUpdate($id);
        if (!$user instanceof User) {
            throw new \RuntimeException('User not found.');
        }

        return $user;
    }

    private function assertCanManage(User $actor): void
    {
        if (!$this->policy->canManage($actor) || !PermissionMatrix::allows($actor, Permission::USER_MANAGE)) {
            throw new \DomainException('You are not allowed to manage users.');
        }
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must contain at least 8 characters.');
        }
    }
}
