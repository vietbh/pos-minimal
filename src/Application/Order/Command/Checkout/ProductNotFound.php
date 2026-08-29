<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

use App\Application\Common\Exception\ApplicationException;

final class ProductNotFound extends ApplicationException
{
    public function __construct(int $productId)
    {
        parent::__construct(
            sprintf('Product %d was not found.', $productId),
        );
    }
}
