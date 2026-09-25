<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\ChangePasswordService;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ChangePasswordServiceTest extends TestCase
{
    public function testChangesPasswordWhenCurrentPasswordIsValid(): void
    {
        $user = new User('password-test');
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $hasher->expects(self::once())->method('isPasswordValid')->with($user, 'old-password')->willReturn(true);
        $hasher->expects(self::once())->method('hashPassword')->with($user, 'new-password')->willReturn('hashed-new');
        $em->expects(self::once())->method('flush');

        $service = new ChangePasswordService($hasher, $em);
        $service->change($user, 'old-password', 'new-password', 'new-password');

        self::assertSame('hashed-new', $user->getPassword());
    }

    public function testRejectsWrongCurrentPassword(): void
    {
        $user = new User('password-test-wrong');
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $hasher->method('isPasswordValid')->willReturn(false);
        $em->expects(self::never())->method('flush');

        $this->expectException(\DomainException::class);
        (new ChangePasswordService($hasher, $em))->change($user, 'wrong', 'new-password', 'new-password');
    }
}
