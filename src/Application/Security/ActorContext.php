<?php

declare(strict_types=1);

namespace App\Application\Security;

final readonly class ActorContext
{
    public function __construct(
        public int $userId,
        public ?string $sessionId = null,
        public ?string $requestId = null,
    ) {
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException(
                'Actor user ID must be greater than zero.',
            );
        }
    }
}
