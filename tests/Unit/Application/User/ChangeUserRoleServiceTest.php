<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Security\Permission;
use App\Application\Security\PermissionMatrix;
use App\Application\Security\UserManagementPolicy;
use App\Application\User\ChangeUserRoleService;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class ChangeUserRoleServiceTest extends TestCase
{
    public function testAdminCannotPromoteUserToAdmin(): void
    {
        $actor = new User('admin');
        $actor->grantRole(UserRole::ADMIN);
        $target = new User('cashier');
        $this->setId($actor, 1);
        $this->setId($target, 2);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findByIdForUpdate')->with(2)->willReturn($target);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager(), new UserManagementPolicy());

        $this->expectException(\DomainException::class);
        $service->change($actor, 2, UserRole::ADMIN);
    }

    public function testRootCanPromoteUserToAdmin(): void
    {
        $actor = new User('root');
        $actor->grantRole(UserRole::ROOT);
        $target = new User('cashier');
        $this->setId($actor, 1);
        $this->setId($target, 2);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findByIdForUpdate')->with(2)->willReturn($target);
        $users->expects(self::once())->method('save')->with($target);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);
        $audits->expects(self::once())->method('save')->with(self::isInstanceOf(AuditLog::class));

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager(), new UserManagementPolicy());
        $service->change($actor, 2, UserRole::ADMIN);

        self::assertTrue($target->hasRole(UserRole::ADMIN));
        self::assertTrue(PermissionMatrix::allows($actor, Permission::USER_MANAGE));
    }

    public function testRegularUserCannotManageRoles(): void
    {
        $actor = new User('user');
        $users = $this->createMock(UserRepositoryInterface::class);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager(), new UserManagementPolicy());

        $this->expectException(\DomainException::class);
        $service->change($actor, 2, UserRole::ADMIN);
    }

    public function testRootCannotCreateOrGrantRootThroughUserManagement(): void
    {
        $actor = new User('root');
        $actor->grantRole(UserRole::ROOT);
        $target = new User('admin');
        $this->setId($actor, 1);
        $this->setId($target, 2);

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findByIdForUpdate')->with(2)->willReturn($target);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);

        $service = new ChangeUserRoleService($users, $audits, $this->transactionManager(), new UserManagementPolicy());

        $this->expectException(\DomainException::class);
        $service->change($actor, 2, UserRole::ROOT);
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
