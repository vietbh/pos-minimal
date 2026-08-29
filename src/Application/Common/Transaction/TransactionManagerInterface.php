<?php

declare(strict_types=1);

namespace App\Application\Common\Transaction;

interface TransactionManagerInterface
{
    /**
     * @template T
     *
     * @param callable(TransactionContextInterface): T $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed;
}
