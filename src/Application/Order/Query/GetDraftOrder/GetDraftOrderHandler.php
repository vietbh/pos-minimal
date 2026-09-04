<?php

declare(strict_types=1);

namespace App\Application\Order\Query\GetDraftOrder;

use App\Application\Order\Query\OrderQueryRepositoryInterface;

final readonly class GetDraftOrderHandler
{
    public function __construct(
        private OrderQueryRepositoryInterface $orderQueryRepository,
    ) {
    }

    public function __invoke(
        GetDraftOrderInput $input,
    ): ?DraftOrderResult {
        if ($input->orderId <= 0) {
            throw new \InvalidArgumentException(
                'Order ID must be greater than zero.',
            );
        }

        return $this->orderQueryRepository->findDraftById(
            $input->orderId,
        );
    }
}
