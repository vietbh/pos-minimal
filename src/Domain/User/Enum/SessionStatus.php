<?php

declare(strict_types=1);

namespace App\Domain\User\Enum;

enum SessionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case LOGGED_OUT = 'LOGGED_OUT';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isLoggedOut(): bool
    {
        return $this === self::LOGGED_OUT;
    }

    public function isRevoked(): bool
    {
        return $this === self::REVOKED;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::LOGGED_OUT,
            self::REVOKED,
            self::EXPIRED => true,
            self::ACTIVE => false,
        };
    }
}
