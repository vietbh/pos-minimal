<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;

final class PermissionMatrix
{
    /**
     * @return list<Permission>
     */
    public static function permissionsFor(User $user): array
    {
        $permissions = [
            Permission::POS_ACCESS,
            Permission::POS_CHECKOUT,

            Permission::ORDER_VIEW,

            Permission::CUSTOMER_VIEW,
            Permission::CUSTOMER_CREATE,
            Permission::CUSTOMER_EDIT,

            Permission::DEBT_VIEW,
            Permission::DEBT_PAYMENT,

            Permission::PRODUCT_VIEW,

            Permission::STOCK_VIEW,
            Permission::STOCK_HISTORY_VIEW,
        ];

        if (
            $user->hasRole(UserRole::ADMIN)
            || $user->hasRole(UserRole::ROOT)
        ) {
            $permissions = array_merge(
                $permissions,
                [
                    Permission::ORDER_CANCEL,
                    Permission::ORDER_REFUND,

                    Permission::PRODUCT_CREATE,
                    Permission::PRODUCT_EDIT,
                    Permission::PRODUCT_PRICE_CHANGE,
                    Permission::PRODUCT_ACTIVATE,

                    Permission::STOCK_ADJUST,

                    Permission::STATISTICS_VIEW,

                    Permission::AUDIT_VIEW,

                    Permission::SESSION_VIEW,
                    Permission::SESSION_REVOKE,

                    Permission::USER_MANAGE,
                ],
            );
        }

        return array_values(
            array_unique(
                $permissions,
                SORT_REGULAR,
            ),
        );
    }

    public static function allows(
        User $user,
        Permission $permission,
    ): bool {
        foreach (self::permissionsFor($user) as $granted) {
            if ($granted === $permission) {
                return true;
            }
        }

        return false;
    }
}
