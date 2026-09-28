<?php

declare(strict_types=1);

namespace App\Domain\Import;

enum ImportStatus: string
{
    case UPLOADED = 'UPLOADED';
    case VALIDATING = 'VALIDATING';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case READY = 'READY';
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case PARTIAL = 'PARTIAL';
    case FAILED = 'FAILED';
}
