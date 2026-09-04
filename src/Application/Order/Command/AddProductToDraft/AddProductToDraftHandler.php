<?php

declare(strict_types=1);

namespace App\Application\Order\Command\AddProductToDraft;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;

final class AddProductToDraftHandler
{
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(
        AddProductToDraftInput $input,
    ): AddProductToDraftResult {
        return $this->transactionManager->run(
            function (TransactionContextInterface $context) use ($input): AddProductToDraftResult {
                $order = $this->orderRepository->findById($input->orderId);

                if ($order === null) {
                    throw new \DomainException('Order not found.');
                }

                if (!$order->isDraft()) {
                    throw new \DomainException(
                        'Products can only be added to a draft order.',
                    );
                }

                $product = $this->productRepository->findById(
                    $input->productId,
                );

                if ($product === null) {
                    throw new \DomainException('Product not found.');
                }

                if (!$product->isActive()) {
                    throw new \DomainException(
                        'Inactive products cannot be added to an order.',
                    );
                }

                $existingItem = null;

                foreach ($order->getItems() as $item) {
                    if ($item->getProduct()->getId() === $product->getId()) {
                        $existingItem = $item;
                        break;
                    }
                }

                if ($existingItem !== null) {
                    $existingItem->changeQuantity(
                        $existingItem->getQuantity() + $input->quantity,
                    );

                    $item = $existingItem;
                } else {
                    $item = new OrderItem(
                        product: $product,
                        quantity: $input->quantity,
                        unitPrice: $product->getSellingPrice(),
                    );

                    $order->addItem($item);
                }

                $order->recalculateTotals();

                $context->flush();

                return new AddProductToDraftResult(
                    orderId: $order->getId() ?? throw new \LogicException(
                        'Order ID is missing.',
                    ),
                    orderNumber: $order->getOrderNumber()->value(),
                    status: $order->getStatus()->value,
                    item: [
                        'id' => $item->getId(),
                        'productId' => $item->getProduct()->getId(),
                        'productName' => $item->getProductName(),
                        'sku' => $item->getSku(),
                        'unitPrice' => $item->getUnitPrice()->toDecimal(),
                        'quantity' => $item->getQuantity(),
                        'subtotal' => $item->getSubtotal()->toDecimal(),
                    ],
                    subtotal: $order->getSubtotal()->toDecimal(),
                    total: $order->getTotal()->toDecimal(),
                );
            },
        );
    }
}
