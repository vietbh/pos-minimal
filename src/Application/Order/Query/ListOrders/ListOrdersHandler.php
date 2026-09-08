<?php

declare(strict_types=1);

namespace App\Application\Order\Query\ListOrders;

use App\Application\Order\Query\OrderQueryRepositoryInterface;

final readonly class ListOrdersHandler
{
    public function __construct(
        private OrderQueryRepositoryInterface $repository,
    ) {
    }

    public function __invoke(ListOrdersInput $input): OrderListResult
    {
        return $this->repository->listOrders($input);
    }
}
