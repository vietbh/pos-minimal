<?php

declare(strict_types=1);

namespace App\Application\Order\Command\ChangeDraftItemQuantity;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Repository\OrderRepositoryInterface;

final readonly class ChangeDraftItemQuantityHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(
        ChangeDraftItemQuantityInput $input,
    ): ChangeDraftItemQuantityResult {
        $this->validateInput($input);

        return $this->transactionManager->run(
            function (
                TransactionContextInterface $transaction,
            ) use ($input): ChangeDraftItemQuantityResult {
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

                $item->changeQuantity(
                    $input->quantity,
                );

                $order->recalculateTotals();

                $this->orderRepository->save($order);

                $transaction->flush();

                $orderId = $order->getId();

                if ($orderId === null) {
                    throw new \LogicException(
                        'Order ID was not generated after flush.',
                    );
                }

                $itemId = $item->getId();

                if ($itemId === null) {
                    throw new \LogicException(
                        'Order item ID was not generated after flush.',
                    );
                }

                return new ChangeDraftItemQuantityResult(
                    orderId: $orderId,
                    orderNumber: $order
                        ->getOrderNumber()
                        ->value(),
                    status: $order->getStatus(),
                    item: [
                        'id' => $itemId,
                        'productId' => $item
                            ->getProduct()
                            ->getId(),
                        'productName' => $item->getProductName(),
                        'sku' => $item->getSku(),
                        'unitPrice' => $item
                            ->getUnitPrice()
                            ->toDecimal(),
                        'quantity' => $item->getQuantity(),
                        'subtotal' => $item
                            ->getSubtotal()
                            ->toDecimal(),
                    ],
                    subtotal: $order->getSubtotal(),
                    total: $order->getTotal(),
                );
            },
        );
    }

    private function validateInput(
        ChangeDraftItemQuantityInput $input,
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

        if ($input->quantity <= 0) {
            throw new \InvalidArgumentException(
                'Order item quantity must be greater than zero.',
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
