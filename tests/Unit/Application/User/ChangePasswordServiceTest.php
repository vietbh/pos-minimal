<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\User\ChangePasswordService;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ChangePasswordServiceTest extends TestCase
{
    public function testChangesPasswordWhenCurrentPasswordIsValid(): void
    {
        $user = new User('password-test');
        $this->setId($user, 1);
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $users = $this->createMock(UserRepositoryInterface::class);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);
        $users->expects(self::once())->method('findByIdForUpdate')->with(1)->willReturn($user);
        $users->expects(self::once())->method('save')->with($user);
        $audits->expects(self::once())->method('save');
        $hasher->expects(self::once())->method('isPasswordValid')->with($user, 'old-password')->willReturn(true);
        $hasher->expects(self::once())->method('hashPassword')->with($user, 'new-password')->willReturn('hashed-new');

        $service = new ChangePasswordService($hasher, $users, $audits, $this->transactionManager());
        $service->change($user, 'old-password', 'new-password', 'new-password');

        self::assertSame('hashed-new', $user->getPassword());
    }

    public function testRejectsWrongCurrentPassword(): void
    {
        $user = new User('password-test-wrong');
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $users = $this->createMock(UserRepositoryInterface::class);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);
        $hasher->method('isPasswordValid')->willReturn(false);
        $users->expects(self::never())->method('findByIdForUpdate');

        $this->expectException(\DomainException::class);
        (new ChangePasswordService($hasher, $users, $audits, $this->transactionManager()))->change($user, 'wrong', 'new-password', 'new-password');
    }

    private function transactionManager(): TransactionManagerInterface
    {
        return new class implements TransactionManagerInterface {
            public function run(callable $operation): mixed
            {
                return $operation(new class implements TransactionContextInterface {
                    public function flush(): void {}
                });
            }
        };
    }

    private function setId(User $user, int $id): void
    {
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);
    }
}
