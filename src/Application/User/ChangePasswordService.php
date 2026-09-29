<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class ChangePasswordService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepositoryInterface $userRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function change(
        User $user,
        string $currentPassword,
        string $newPassword,
        string $confirmation,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        if (!$user->hasPassword() || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new \DomainException('Current password is incorrect.');
        }
        if ($newPassword !== $confirmation) {
            throw new \DomainException('New password confirmation does not match.');
        }
        if (strlen($newPassword) < 8) {
            throw new \DomainException('New password must contain at least 8 characters.');
        }
        if ($currentPassword === $newPassword) {
            throw new \DomainException('New password must be different from the current password.');
        }

        $id = $user->getId();
        if ($id === null) {
            throw new \RuntimeException('User must be persisted before changing password.');
        }

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($id, $newPassword, $user, $ipAddress, $userAgent): void {
                $target = $this->userRepository->findByIdForUpdate($id);
                if (!$target instanceof User) {
                    throw new \RuntimeException('User not found.');
                }

                $target->setPasswordHash($this->passwordHasher->hashPassword($target, $newPassword));
                $this->userRepository->save($target);
                $this->auditLogRepository->save(new AuditLog(
                    action: 'USER_PASSWORD_CHANGED',
                    user: $user,
                    entityType: 'User',
                    entityId: (string) $target->getId(),
                    newValues: ['passwordChanged' => true],
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                ));
                $transaction->flush();
            },
        );
    }
}
