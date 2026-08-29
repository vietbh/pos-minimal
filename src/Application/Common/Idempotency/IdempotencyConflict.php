<?php

declare(strict_types=1);

namespace App\Application\Common\Idempotency;

use App\Application\Common\Exception\ApplicationException;

final class IdempotencyConflict extends ApplicationException
{
    public function __construct()
    {
        parent::__construct(
            'The idempotency key was already used with a different request.',
        );
    }
}
