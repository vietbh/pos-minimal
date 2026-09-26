<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Security\Permission;
use App\Application\Security\PermissionMatrix;
use App\Application\User\ChangeUserRoleService;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class ChangeUserRoleServiceTest extends TestCase
{
    public function testAdminCanChangeAnotherUserToAdmin(): void
    {
        $actor = new User('admin');
        $actor->grantRole(UserRole::ADMIN);
        $target = new User('cashier');
        $this->setId($actor, 1);
        $this->setId($target, 2);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findById')->with(2)->willReturn($target);
        $users->expects(self::once())->method('save')->with($target);

        $audits = $this->createMock(AuditLogRepositoryInterface::class);
        $audits->expects(self::once())->method('save')->with(self::callback(
            static fn (AuditLog $audit): bool =>
                $audit->getAction() === 'USER_ROLE_CHANGED'
                && $audit->getEntityId() === '2'
                && $audit->getNewValues() === ['roles' => ['ROLE_ADMIN', 'ROLE_USER']],
        ));

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager());
        $service->change($actor, 2, UserRole::ADMIN);

        self::assertTrue($target->hasRole(UserRole::ADMIN));
    }

    public function testRegularUserCannotManageRoles(): void
    {
        $actor = new User('user');
        $target = new User('other');

        $users = $this->createMock(UserRepositoryInterface::class);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager());

        $this->expectException(\DomainException::class);
        $service->change($actor, 2, UserRole::ADMIN);
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $actor = new User('admin');
        $actor->grantRole(UserRole::ADMIN);
        $this->setId($actor, 1);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findById')->with(1)->willReturn($actor);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager());

        $this->expectException(\DomainException::class);
        $service->change($actor, 1, UserRole::USER);
    }

    public function testAdminCannotAssignRoot(): void
    {
        $actor = new User('admin');
        $actor->grantRole(UserRole::ADMIN);
        $target = new User('user');
        $this->setId($actor, 1);
        $this->setId($target, 2);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findById')->with(2)->willReturn($target);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager());

        $this->expectException(\DomainException::class);
        $service->change($actor, 2, UserRole::ROOT);
    }

    public function testRootCanAssignRoot(): void
    {
        $actor = new User('root');
        $actor->grantRole(UserRole::ROOT);
        $target = new User('admin');
        $this->setId($actor, 1);
        $this->setId($target, 2);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findById')->with(2)->willReturn($target);
        $users->expects(self::once())->method('save')->with($target);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);
        $audits->expects(self::once())->method('save');

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager());
        $service->change($actor, 2, UserRole::ROOT);

        self::assertTrue($target->hasRole(UserRole::ROOT));
        self::assertTrue(PermissionMatrix::allows($actor, Permission::USER_MANAGE));
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
