<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetOrder;

use App\Application\Order\Query\OrderQueryRepositoryInterface;

final readonly class GetOrderHandler
{
    public function __construct(private OrderQueryRepositoryInterface $repository)
    {
    }

    public function __invoke(GetOrderInput $input): ?OrderDetailResult
    {
        return $this->repository->findOrderById($input->orderId);
    }
}
