<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Order\Query\GetDraftOrder\DraftOrderCustomerResult;
use App\Application\Order\Query\GetDraftOrder\DraftOrderItemResult;
use App\Application\Order\Query\GetDraftOrder\DraftOrderResult;
use App\Application\Order\Query\GetOrder\OrderDebtResult;
use App\Application\Order\Query\GetOrder\OrderDetailResult;
use App\Application\Order\Query\GetOrder\OrderItemResult;
use App\Application\Order\Query\GetOrder\OrderExternalTransactionResult;
use App\Application\Order\Query\GetOrder\OrderPaymentResult;
use App\Application\Order\Query\ListOrders\ListOrdersInput;
use App\Application\Order\Query\ListOrders\OrderListItemResult;
use App\Application\Order\Query\ListOrders\OrderListResult;
use App\Application\Order\Query\OrderQueryRepositoryInterface;
use App\Domain\Debt\Debt;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Payment\ExternalPaymentTransaction;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;

final class OrderQueryRepository implements OrderQueryRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findDraftById(int $orderId): ?DraftOrderResult
    {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException('Order ID must be greater than zero.');
        }

        /** @var Order|null $order */
        $order = $this->entityManager->createQueryBuilder()
            ->select('o', 'c', 'i')
            ->from(Order::class, 'o')
            ->leftJoin('o.customer', 'c')
            ->leftJoin('o.items', 'i')
            ->where('o.id = :orderId')
            ->andWhere('o.status = :status')
            ->setParameter('orderId', $orderId)
            ->setParameter('status', OrderStatus::DRAFT)
            ->getQuery()
            ->getOneOrNullResult();

        if ($order === null) {
            return null;
        }

        $id = $order->getId();
        if ($id === null) {
            throw new \LogicException('Cannot create a draft result for an unsaved order.');
        }

        $customer = $order->getCustomer();
        $customerResult = null;
        if ($customer !== null) {
            $customerId = $customer->getId();
            if ($customerId === null) {
                throw new \LogicException('Cannot create a draft result with an unsaved customer.');
            }
            $customerResult = new DraftOrderCustomerResult($customerId, $customer->getName(), $customer->getPhone());
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            $itemId = $item->getId();
            $productId = $item->getProduct()->getId();
            if ($itemId === null || $productId === null) {
                throw new \LogicException('Cannot create a draft result with unsaved data.');
            }
            $items[] = new DraftOrderItemResult(
                $itemId,
                $productId,
                $item->getProductName(),
                $item->getSku(),
                $item->getUnitPrice()->minorUnits(),
                $item->getQuantity(),
                $item->getSubtotal()->minorUnits(),
            );
        }

        return new DraftOrderResult(
            $id,
            $order->getOrderNumber()->value(),
            $order->getStatus(),
            $customerResult,
            $items,
            $order->getSubtotal()->minorUnits(),
            $order->getTotal()->minorUnits(),
        );
    }

    public function listOrders(ListOrdersInput $input): OrderListResult
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'o.id AS id',
                'o.orderNumber AS orderNumber',
                'o.status AS status',
                'c.id AS customerId',
                'c.name AS customerName',
                'o.total AS total',
                'o.createdAt AS createdAt',
                'COALESCE(SUM(p.amount), 0) AS paidAmount',
            )
            ->from(Order::class, 'o')
            ->leftJoin('o.customer', 'c')
            ->leftJoin('o.payments', 'p')
            ->groupBy('o.id, o.orderNumber, o.status, c.id, c.name, o.total, o.createdAt')
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        $search = trim($input->search);
        if ($search !== '') {
            $qb->andWhere('(o.orderNumber LIKE :search OR c.name LIKE :search OR c.phone LIKE :search)')
                ->setParameter('search', '%'.$search.'%');
        }

        if ($input->status !== null) {
            $qb->andWhere('o.status = :status')->setParameter('status', $input->status);
        }

        if ($input->from !== null) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $input->from);
        }

        if ($input->to !== null) {
            $qb->andWhere('o.createdAt < :toExclusive')->setParameter('toExclusive', $input->to->modify('+1 day'));
        }

        $countQb = clone $qb;
        $countQb->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy')
            ->select('COUNT(DISTINCT o.id)');
        $totalItems = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($totalItems / $input->perPage));
        $page = min($input->page, $totalPages);

        $rows = $qb
            ->setFirstResult(($page - 1) * $input->perPage)
            ->setMaxResults($input->perPage)
            ->getQuery()
            ->getArrayResult();

        $orderIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $debtByOrderId = [];
        if ($orderIds !== []) {
            $debts = $this->entityManager->createQueryBuilder()
                ->select('d')
                ->from(Debt::class, 'd')
                ->where('IDENTITY(d.order) IN (:orderIds)')
                ->setParameter('orderIds', $orderIds)
                ->getQuery()
                ->getResult();
            foreach ($debts as $debtEntity) {
                if ($debtEntity instanceof Debt && $debtEntity->getOrder()->getId() !== null) {
                    $debtByOrderId[$debtEntity->getOrder()->getId()] = $debtEntity;
                }
            }
        }

        $items = [];
        foreach ($rows as $row) {
            $total = Money::fromDecimal((string) $row['total'])->toDecimal();
            $paid = Money::fromDecimal((string) $row['paidAmount']);
            $debtEntity = $debtByOrderId[(int) $row['id']] ?? null;
            if ($debtEntity instanceof Debt) {
                $paid = $paid->add($debtEntity->getPaidAmount());
            }
            $paid = $paid->isGreaterThanOrEqual(Money::fromDecimal($total))
                ? Money::fromDecimal($total)
                : $paid;
            $paidDecimal = $paid->toDecimal();
            $debt = $this->subtractDecimal($total, $paidDecimal);

            $items[] = new OrderListItemResult(
                id: (int) $row['id'],
                orderNumber: (string) $row['orderNumber'],
                status: $row['status'] instanceof OrderStatus ? $row['status'] : OrderStatus::from((string) $row['status']),
                customerId: $row['customerId'] !== null ? (int) $row['customerId'] : null,
                customerName: $row['customerName'] !== null ? (string) $row['customerName'] : null,
                total: $total,
                paidAmount: $paidDecimal,
                debtAmount: $debt,
                createdAt: $this->toDateTimeImmutable($row['createdAt']),
            );
        }

        return new OrderListResult($items, $page, $input->perPage, $totalItems);
    }

    public function findOrderById(int $orderId): ?OrderDetailResult
    {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException('Order ID must be greater than zero.');
        }

        /** @var Order|null $order */
        $order = $this->entityManager->createQueryBuilder()
            ->select('o', 'c', 'u', 'i', 'p')
            ->from(Order::class, 'o')
            ->leftJoin('o.customer', 'c')
            ->leftJoin('o.user', 'u')
            ->leftJoin('o.items', 'i')
            ->leftJoin('o.payments', 'p')
            ->where('o.id = :id')
            ->setParameter('id', $orderId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($order === null || $order->getId() === null) {
            return null;
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            if ($item->getId() === null || $item->getProduct()->getId() === null) {
                throw new \LogicException('Order contains unsaved item data.');
            }
            $items[] = new OrderItemResult(
                $item->getId(),
                $item->getProduct()->getId(),
                $item->getProductName(),
                $item->getSku(),
                $item->getUnitPrice()->toDecimal(),
                $item->getQuantity(),
                $item->getSubtotal()->toDecimal(),
            );
        }

        $payments = [];
        foreach ($order->getPayments() as $payment) {
            if ($payment->getId() === null) {
                throw new \LogicException('Order contains an unsaved payment.');
            }
            $payments[] = new OrderPaymentResult(
                $payment->getId(),
                $payment->getAmount()->toDecimal(),
                $payment->getMethod()->value,
                $payment->getReference(),
                $payment->getUser()->getUserIdentifier(),
                $payment->getCreatedAt(),
            );
        }

        $externalTransaction = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(ExternalPaymentTransaction::class, 't')
            ->where('IDENTITY(t.matchedOrder) = :orderId')
            ->setParameter('orderId', $order->getId())
            ->orderBy('t.occurredAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $externalTransactionResult = $externalTransaction instanceof ExternalPaymentTransaction && $externalTransaction->getId() !== null
            ? new OrderExternalTransactionResult(
                id: $externalTransaction->getId(),
                provider: $externalTransaction->getProvider(),
                externalTransactionId: $externalTransaction->getExternalTransactionId(),
                amount: $externalTransaction->getAmount()->toDecimal(),
                description: $externalTransaction->getDescription(),
                reference: $externalTransaction->getTransactionReference(),
                occurredAt: $externalTransaction->getOccurredAt(),
                status: $externalTransaction->getStatus(),
            )
            : null;

        $debt = $this->findDebtForOrder($order);
        $orderPaid = $order->getPaidAmount();
        if ($debt instanceof OrderDebtResult) {
            $orderPaid = $orderPaid->add(Money::fromDecimal($debt->paidAmount));
        }
        if ($orderPaid->isGreaterThanOrEqual($order->getTotal())) {
            $orderPaid = $order->getTotal();
        }
        $orderPaidDecimal = $orderPaid->toDecimal();
        $orderDebtDecimal = $this->subtractDecimal($order->getTotal()->toDecimal(), $orderPaidDecimal);

        return new OrderDetailResult(
            id: $order->getId(),
            orderNumber: $order->getOrderNumber()->value(),
            status: $order->getStatus(),
            customerName: $order->getCustomer()?->getName() ?? 'Walk-in customer',
            customerPhone: $order->getCustomer()?->getPhone(),
            createdBy: $order->getUser()->getUserIdentifier(),
            subtotal: $order->getSubtotal()->toDecimal(),
            total: $order->getTotal()->toDecimal(),
            paidAmount: $orderPaidDecimal,
            debtAmount: $orderDebtDecimal,
            note: $order->getNote(),
            createdAt: $order->getCreatedAt(),
            completedAt: $order->getCompletedAt(),
            cancelledAt: $order->getCancelledAt(),
            items: $items,
            payments: $payments,
            externalTransaction: $externalTransactionResult,
            debt: $debt,
        );
    }

    private function findDebtForOrder(Order $order): ?OrderDebtResult
    {
        $debt = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Debt::class, 'd')
            ->where('d.order = :order')
            ->setParameter('order', $order)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$debt instanceof Debt || $debt->getId() === null) {
            return null;
        }

        return new OrderDebtResult(
            id: $debt->getId(),
            originalAmount: $debt->getOriginalAmount()->toDecimal(),
            paidAmount: $debt->getPaidAmount()->toDecimal(),
            remainingAmount: $debt->getRemainingAmount()->toDecimal(),
            status: $debt->getStatus()->value,
        );
    }

    private function subtractDecimal(string $left, string $right): string
    {
        $leftMoney = Money::fromDecimal($left);
        $rightMoney = Money::fromDecimal($right);
        if ($rightMoney->isGreaterThanOrEqual($leftMoney)) {
            return '0.00';
        }
        return $leftMoney->subtract($rightMoney)->toDecimal();
    }

    private function toDateTimeImmutable(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        return new \DateTimeImmutable((string) $value);
    }
}
