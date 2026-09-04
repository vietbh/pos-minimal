<?php

declare(strict_types=1);

namespace App\Application\Order\Command\DetachCustomerFromDraft;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Order\Repository\OrderRepositoryInterface;

final readonly class DetachCustomerFromDraftHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(
        DetachCustomerFromDraftInput $input,
    ): DetachCustomerFromDraftResult {
        if ($input->orderId <= 0) {
            throw new \InvalidArgumentException(
                'Order ID must be greater than zero.',
            );
        }

        return $this->transactionManager->run(
            function (
                TransactionContextInterface $transaction,
            ) use ($input): DetachCustomerFromDraftResult {
                $order = $this->orderRepository->findById(
                    $input->orderId,
                );

                if ($order === null) {
                    throw new \DomainException(
                        sprintf(
                            'Order %d was not found.',
                            $input->orderId,
                        ),
                    );
                }

                if (!$order->isDraft()) {
                    throw new \DomainException(
                        'Customer can only be changed while order is draft.',
                    );
                }

                $order->changeCustomer(null);

                $this->orderRepository->save($order);
                $transaction->flush();

                $orderId = $order->getId();

                if ($orderId === null) {
                    throw new \LogicException(
                        'Order ID was not generated after flush.',
                    );
                }

                return new DetachCustomerFromDraftResult(
                    orderId: $orderId,
                    orderNumber: $order
                        ->getOrderNumber()
                        ->value(),
                    status: $order->getStatus(),
                    customerDetached: $order->getCustomer() === null,
                );
            },
        );
    }
}
