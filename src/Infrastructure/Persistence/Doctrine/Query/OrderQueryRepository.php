<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Order\Query\GetDraftOrder\DraftOrderCustomerResult;
use App\Application\Order\Query\GetDraftOrder\DraftOrderItemResult;
use App\Application\Order\Query\GetDraftOrder\DraftOrderResult;
use App\Application\Order\Query\OrderQueryRepositoryInterface;
use App\Domain\Order\Order;
use Doctrine\ORM\EntityManagerInterface;

final class OrderQueryRepository implements OrderQueryRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findDraftById(
        int $orderId,
    ): ?DraftOrderResult {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException(
                'Order ID must be greater than zero.',
            );
        }

        /** @var Order|null $order */
        $order = $this->entityManager
            ->createQueryBuilder()
            ->select('o', 'c', 'i')
            ->from(Order::class, 'o')
            ->leftJoin('o.customer', 'c')
            ->leftJoin('o.items', 'i')
            ->where('o.id = :orderId')
            ->andWhere('o.status = :status')
            ->setParameter('orderId', $orderId)
            ->setParameter(
                'status',
                \App\Domain\Order\Enum\OrderStatus::DRAFT,
            )
            ->getQuery()
            ->getOneOrNullResult();

        if ($order === null) {
            return null;
        }

        $id = $order->getId();

        if ($id === null) {
            throw new \LogicException(
                'Cannot create a draft result for an unsaved order.',
            );
        }

        $customer = $order->getCustomer();

        $customerResult = null;

        if ($customer !== null) {
            $customerId = $customer->getId();

            if ($customerId === null) {
                throw new \LogicException(
                    'Cannot create a draft result with an unsaved customer.',
                );
            }

            $customerResult = new DraftOrderCustomerResult(
                id: $customerId,
                name: $customer->getName(),
                phone: $customer->getPhone(),
            );
        }

        $items = [];

        foreach ($order->getItems() as $item) {
            $itemId = $item->getId();

            if ($itemId === null) {
                throw new \LogicException(
                    'Cannot create a draft result with an unsaved item.',
                );
            }

            $productId = $item->getProduct()->getId();

            if ($productId === null) {
                throw new \LogicException(
                    'Cannot create a draft result with an unsaved product.',
                );
            }

            $items[] = new DraftOrderItemResult(
                id: $itemId,
                productId: $productId,
                productName: $item->getProductName(),
                sku: $item->getSku(),
                unitPrice: $item->getUnitPrice()->minorUnits(),
                quantity: $item->getQuantity(),
                subtotal: $item->getSubtotal()->minorUnits(),
            );
        }

        return new DraftOrderResult(
            id: $id,
            orderNumber: $order
                ->getOrderNumber()
                ->value(),
            status: $order->getStatus(),
            customer: $customerResult,
            items: $items,
            subtotal: $order->getSubtotal()->minorUnits(),
            total: $order->getTotal()->minorUnits(),
        );
    }
}
