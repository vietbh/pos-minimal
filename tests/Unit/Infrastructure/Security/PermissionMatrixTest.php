<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Application\Security\Permission;
use App\Application\Security\PermissionMatrix;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class PermissionMatrixTest extends TestCase
{
    public function testUserGetsOnlyMvpUserPermissions(): void
    {
        $user = new User('user');

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::POS_CHECKOUT,
            ),
        );

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::ORDER_VIEW,
            ),
        );

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::PRODUCT_VIEW,
            ),
        );

        self::assertFalse(
            PermissionMatrix::allows(
                $user,
                Permission::STOCK_ADJUST,
            ),
        );

        self::assertFalse(
            PermissionMatrix::allows(
                $user,
                Permission::AUDIT_VIEW,
            ),
        );

        self::assertFalse(
            PermissionMatrix::allows(
                $user,
                Permission::USER_MANAGE,
            ),
        );
    }

    public function testAdminGetsUserAndAdminPermissions(): void
    {
        $user = new User('admin');
        $user->grantRole(UserRole::ADMIN);

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::POS_CHECKOUT,
            ),
        );

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::STOCK_ADJUST,
            ),
        );

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::ORDER_CANCEL,
            ),
        );

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::PRODUCT_PRICE_CHANGE,
            ),
        );

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::AUDIT_VIEW,
            ),
        );

        self::assertTrue(
            PermissionMatrix::allows(
                $user,
                Permission::USER_MANAGE,
            ),
        );
    }

    public function testRootGetsAdminPermissions(): void
    {
        $user = new User('root');
        $user->grantRole(UserRole::ROOT);

        foreach (Permission::cases() as $permission) {
            self::assertTrue(
                PermissionMatrix::allows(
                    $user,
                    $permission,
                ),
                sprintf(
                    'ROLE_ROOT must have permission %s.',
                    $permission->value,
                ),
            );
        }
    }
}
