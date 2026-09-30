<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Dashboard\DashboardNavigationResolver;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class DashboardNavigationResolverTest extends TestCase
{
    public function testStaffGetsOverviewAndOperations(): void
    {
        $user = new User('staff');
        $resolver = new DashboardNavigationResolver();

        $navigation = $resolver->resolve($user, null);

        self::assertSame('overview', $navigation->activeTab);
        self::assertSame(['overview', 'operations'], array_column($navigation->tabs, 'key'));
    }

    public function testAdminDefaultsToAdministrationWhenPreferenceIsAuto(): void
    {
        $user = new User('admin-default');
        $user->setRoles([UserRole::ADMIN->value]);
        $resolver = new DashboardNavigationResolver();

        $navigation = $resolver->resolve($user, null);

        self::assertSame('administration', $navigation->activeTab);
        self::assertSame('administration', $navigation->defaultTab);
    }

    public function testUserCanPersistAnAllowedDefaultTab(): void
    {
        $user = new User('staff-default');
        $user->changeDashboardDefaultTab('operations');
        $resolver = new DashboardNavigationResolver();

        $navigation = $resolver->resolve($user, null);

        self::assertSame('operations', $navigation->activeTab);
        self::assertSame('operations', $navigation->defaultTab);
    }

    public function testUnauthorizedSavedDefaultFallsBackToOverview(): void
    {
        $user = new User('staff-invalid-default');
        $user->changeDashboardDefaultTab('administration');
        $resolver = new DashboardNavigationResolver();

        $navigation = $resolver->resolve($user, null);

        self::assertSame('overview', $navigation->activeTab);
        self::assertSame('overview', $navigation->defaultTab);
    }

    public function testAdminGetsInsightsAndAdministration(): void
    {
        $user = new User('admin');
        $user->setRoles([UserRole::ADMIN->value]);
        $resolver = new DashboardNavigationResolver();

        $navigation = $resolver->resolve($user, 'administration');

        self::assertSame('administration', $navigation->activeTab);
        self::assertSame(
            ['overview', 'operations', 'insights', 'administration'],
            array_column($navigation->tabs, 'key'),
        );
    }

    public function testUnauthorizedRequestedTabFallsBackToOverview(): void
    {
        $user = new User('staff');
        $resolver = new DashboardNavigationResolver();

        $navigation = $resolver->resolve($user, 'administration');

        self::assertSame('overview', $navigation->activeTab);
    }
}
