<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Application\Security\Permission;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use App\Infrastructure\Security\Voter\PermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PermissionVoterTest extends TestCase
{
    public function testUserIsGrantedAllowedPermission(): void
    {
        $user = new User('cashier');

        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles(),
        );

        $voter = new PermissionVoter();

        self::assertSame(
            1,
            $voter->vote(
                $token,
                null,
                [Permission::POS_CHECKOUT->value],
            ),
        );
    }

    public function testUserIsDeniedAdminPermission(): void
    {
        $user = new User('cashier');

        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles(),
        );

        $voter = new PermissionVoter();

        self::assertSame(
            -1,
            $voter->vote(
                $token,
                null,
                [Permission::STOCK_ADJUST->value],
            ),
        );
    }

    public function testAdminIsGrantedAdminPermission(): void
    {
        $user = new User('admin');
        $user->grantRole(UserRole::ADMIN);

        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles(),
        );

        $voter = new PermissionVoter();

        self::assertSame(
            1,
            $voter->vote(
                $token,
                null,
                [Permission::STOCK_ADJUST->value],
            ),
        );
    }

    public function testRootIsGrantedAdminPermission(): void
    {
        $user = new User('root');
        $user->grantRole(UserRole::ROOT);

        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles(),
        );

        $voter = new PermissionVoter();

        self::assertSame(
            1,
            $voter->vote(
                $token,
                null,
                [Permission::AUDIT_VIEW->value],
            ),
        );
    }

    public function testAnonymousUserIsDenied(): void
    {
        $token = $this->createMock(
            \Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class,
        );

        $token
            ->method('getUser')
            ->willReturn(null);

        $voter = new PermissionVoter();

        self::assertSame(
            -1,
            $voter->vote(
                $token,
                null,
                [Permission::POS_CHECKOUT->value],
            ),
        );
    }
}
