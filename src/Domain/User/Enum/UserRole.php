<?php

declare(strict_types=1);

namespace App\Domain\User\Enum;

enum UserRole: string
{
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';
    case ROOT = 'ROLE_ROOT';

    public function isUser(): bool
    {
        return $this === self::USER;
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isRoot(): bool
    {
        return $this === self::ROOT;
    }
}
