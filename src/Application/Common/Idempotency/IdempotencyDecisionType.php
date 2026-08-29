<?php

declare(strict_types=1);

namespace App\Application\Common\Idempotency;

enum IdempotencyDecisionType: string
{
    case EXECUTE = 'execute';
    case REPLAY = 'replay';
    case IN_PROGRESS = 'in_progress';
}
