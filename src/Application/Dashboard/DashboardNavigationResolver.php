<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Security\Permission;
use App\Application\Security\PermissionMatrix;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;

final class DashboardNavigationResolver
{
    /**
     * @return list<array{key:string,label:string}>
     */
    public function availableTabs(User $user): array
    {
        $tabs = [
            ['key' => 'overview', 'label' => 'app.dashboard_tab_overview'],
        ];

        if (
            PermissionMatrix::allows($user, Permission::POS_ACCESS)
            || PermissionMatrix::allows($user, Permission::ORDER_VIEW)
            || PermissionMatrix::allows($user, Permission::CUSTOMER_VIEW)
            || PermissionMatrix::allows($user, Permission::DEBT_VIEW)
            || PermissionMatrix::allows($user, Permission::PRODUCT_VIEW)
            || PermissionMatrix::allows($user, Permission::STOCK_VIEW)
        ) {
            $tabs[] = ['key' => 'operations', 'label' => 'app.dashboard_tab_operations'];
        }

        if (PermissionMatrix::allows($user, Permission::STATISTICS_VIEW)) {
            $tabs[] = ['key' => 'insights', 'label' => 'app.dashboard_tab_insights'];
        }

        if (
            PermissionMatrix::allows($user, Permission::USER_MANAGE)
            || PermissionMatrix::allows($user, Permission::PAYMENT_BANK_ACCOUNT_MANAGE)
            || PermissionMatrix::allows($user, Permission::SALES_POINT_MANAGE)
            || PermissionMatrix::allows($user, Permission::AUDIT_VIEW)
            || PermissionMatrix::allows($user, Permission::ROOT_ANALYTICS_VIEW)
        ) {
            $tabs[] = ['key' => 'administration', 'label' => 'app.dashboard_tab_administration'];
        }

        return $tabs;
    }

    public function resolve(User $user, ?string $requestedTab): DashboardNavigation
    {
        $tabs = $this->availableTabs($user);
        $allowed = array_column($tabs, 'key');

        $savedPreference = strtolower(trim($user->getDashboardDefaultTab()));
        $default = $savedPreference === 'auto'
            ? $this->roleDefault($user)
            : $savedPreference;

        if (!in_array($default, $allowed, true)) {
            $default = in_array('overview', $allowed, true)
                ? 'overview'
                : ($allowed[0] ?? 'overview');
        }

        $requestedTab = is_string($requestedTab) ? trim(strtolower($requestedTab)) : '';
        $active = in_array($requestedTab, $allowed, true) ? $requestedTab : $default;

        return new DashboardNavigation(
            tabs: $tabs,
            activeTab: $active,
            defaultTab: $default,
        );
    }

    private function roleDefault(User $user): string
    {
        if ($user->hasRole(UserRole::ROOT) || $user->hasRole(UserRole::ADMIN)) {
            return 'administration';
        }

        return 'overview';
    }
}
