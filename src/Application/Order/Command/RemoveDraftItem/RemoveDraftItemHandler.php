<?php

declare(strict_types=1);

namespace App\Application\Order\Command\RemoveDraftItem;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Repository\OrderRepositoryInterface;

final readonly class RemoveDraftItemHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(
        RemoveDraftItemInput $input,
    ): RemoveDraftItemResult {
        $this->validateInput($input);

        return $this->transactionManager->run(
            function (
                TransactionContextInterface $transaction,
            ) use ($input): RemoveDraftItemResult {
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
                        'Order items can only be changed while order is draft.',
                    );
                }

                $item = $this->findItem(
                    $order,
                    $input->itemId,
                );

                if ($item === null) {
                    throw new \DomainException(
                        sprintf(
                            'Order item %d was not found in order %d.',
                            $input->itemId,
                            $input->orderId,
                        ),
                    );
                }

                $order->removeItem($item);

                $this->orderRepository->save($order);

                $transaction->flush();

                $orderId = $order->getId();

                if ($orderId === null) {
                    throw new \LogicException(
                        'Order ID was not generated after flush.',
                    );
                }

                return new RemoveDraftItemResult(
                    orderId: $orderId,
                    orderNumber: $order
                        ->getOrderNumber()
                        ->value(),
                    status: $order->getStatus(),
                    remainingItemCount: $order->getItems()->count(),
                    subtotal: $order->getSubtotal(),
                    total: $order->getTotal(),
                );
            },
        );
    }

    private function validateInput(
        RemoveDraftItemInput $input,
    ): void {
        if ($input->orderId <= 0) {
            throw new \InvalidArgumentException(
                'Order ID must be greater than zero.',
            );
        }

        if ($input->itemId <= 0) {
            throw new \InvalidArgumentException(
                'Order item ID must be greater than zero.',
            );
        }
    }

    private function findItem(
        Order $order,
        int $itemId,
    ): ?OrderItem {
        foreach ($order->getItems() as $item) {
            if ($item->getId() === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
