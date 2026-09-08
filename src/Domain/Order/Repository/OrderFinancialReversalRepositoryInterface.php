<?php

declare(strict_types=1);

namespace App\Domain\Order\Repository;

use App\Domain\Order\OrderFinancialReversal;

interface OrderFinancialReversalRepositoryInterface
{
    public function save(OrderFinancialReversal $reversal): void;
    public function findByOrderId(int $orderId): ?OrderFinancialReversal;
}
