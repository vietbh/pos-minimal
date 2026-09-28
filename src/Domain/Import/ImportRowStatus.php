<?php

declare(strict_types=1);

namespace App\Domain\Import;

enum ImportRowStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
}
