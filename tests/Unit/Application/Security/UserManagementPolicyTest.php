<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Security;

use App\Application\Security\UserManagementPolicy;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class UserManagementPolicyTest extends TestCase
{
    private UserManagementPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new UserManagementPolicy();
    }

    public function testRegularUserCannotSeeAdminOrRoot(): void
    {
        $user = new User('user');
        $admin = new User('admin');
        $admin->grantRole(UserRole::ADMIN);
        $root = new User('root');
        $root->grantRole(UserRole::ROOT);

        self::assertFalse($this->policy->canManage($user));
        self::assertFalse($this->policy->canView($user, $admin));
        self::assertFalse($this->policy->canView($user, $root));
    }

    public function testAdminCanSeeNonRootAccountsButCannotManageAdminOrRoot(): void
    {
        $admin = new User('admin');
        $admin->grantRole(UserRole::ADMIN);
        $user = new User('user');
        $otherAdmin = new User('other-admin');
        $otherAdmin->grantRole(UserRole::ADMIN);
        $root = new User('root');
        $root->grantRole(UserRole::ROOT);

        self::assertTrue($this->policy->canView($admin, $user));
        self::assertTrue($this->policy->canMutate($admin, $user));
        self::assertTrue($this->policy->canView($admin, $otherAdmin));
        self::assertFalse($this->policy->canMutate($admin, $otherAdmin));
        self::assertFalse($this->policy->canView($admin, $root));
        self::assertFalse($this->policy->canCreateRole($admin, UserRole::ADMIN));
        self::assertTrue($this->policy->canCreateRole($admin, UserRole::USER));
    }

    public function testRootCanManageAdminAndUserButRootItselfRemainsHidden(): void
    {
        $root = new User('root');
        $root->grantRole(UserRole::ROOT);
        $admin = new User('admin');
        $admin->grantRole(UserRole::ADMIN);
        $user = new User('user');

        self::assertTrue($this->policy->canView($root, $admin));
        self::assertTrue($this->policy->canMutate($root, $admin));
        self::assertTrue($this->policy->canView($root, $user));
        self::assertFalse($this->policy->canView($root, $root));
        self::assertFalse($this->policy->canCreateRole($root, UserRole::ROOT));
        self::assertTrue($this->policy->canCreateRole($root, UserRole::ADMIN));
        self::assertTrue($this->policy->canSwitchTo($root, $admin));
        self::assertFalse($this->policy->canSwitchTo($root, $root));
    }
}
