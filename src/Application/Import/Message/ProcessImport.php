<?php

declare(strict_types=1);

namespace App\Application\Import\Message;

final readonly class ProcessImport
{
    public function __construct(public int $importBatchId) {}
}
