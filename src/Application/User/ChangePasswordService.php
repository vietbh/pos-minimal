<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class ChangePasswordService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {}

    public function change(User $user, string $currentPassword, string $newPassword, string $confirmation): void
    {
        if (!$user->hasPassword() || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new \DomainException('Current password is incorrect.');
        }
        if ($newPassword !== $confirmation) throw new \DomainException('New password confirmation does not match.');
        if (strlen($newPassword) < 8) throw new \DomainException('New password must contain at least 8 characters.');
        if ($currentPassword === $newPassword) throw new \DomainException('New password must be different from the current password.');
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();
    }
}
