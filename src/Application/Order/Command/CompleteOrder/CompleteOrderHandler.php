<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CompleteOrder;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\User\User;

final readonly class CompleteOrderHandler
{
    public function __construct(
        private TransactionManagerInterface $transactions,
        private CompleteOrderService $completion,
    ) {
    }

    public function handle(int $orderId, User $actor, ?string $requestId = null): CompleteOrderResult
    {
        return $this->transactions->run(function () use ($orderId, $actor, $requestId): CompleteOrderResult {
            $order = $this->completion->completePaidOrder($orderId, $actor, $requestId, 'POS');

            return new CompleteOrderResult(
                orderId: $order->getId() ?? throw new \LogicException('Order ID is missing.'),
                orderNumber: $order->getOrderNumber()->value(),
                status: $order->getStatus()->value,
                paidAmount: $order->getPaidAmount()->toDecimal(),
                debtAmount: $order->getDebtAmount()->toDecimal(),
                total: $order->getTotal()->toDecimal(),
            );
        });
    }
}
