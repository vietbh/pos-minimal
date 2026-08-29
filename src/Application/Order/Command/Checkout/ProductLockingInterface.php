<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

use App\Domain\Product\Product;

interface ProductLockingInterface
{
    /**
     * Load a Product and acquire the pessimistic write lock
     * for the current transaction.
     */
    public function lock(int $productId): Product;
}
