<?php

declare(strict_types=1);

namespace App\Application\Common\Transaction;

interface TransactionContextInterface
{
    /**
     * Synchronize pending persistence changes while keeping
     * the current database transaction open.
     */
    public function flush(): void;
}
