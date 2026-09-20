<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CompleteOrder;

use App\Application\Order\Command\Checkout\ProductLockingInterface;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Order\Order;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Payment\Enum\PaymentReferenceStatus;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;

final readonly class CompleteOrderService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ProductLockingInterface $productLocking,
        private StockMovementRepositoryInterface $stockMovements,
        private AuditLogRepositoryInterface $auditLogs,
        private ?PaymentReferenceRepositoryInterface $paymentReferences = null,
    ) {
    }

    /**
     * Completes a paid draft order inside the caller's transaction.
     *
     * The caller owns the transaction boundary so this operation can be used
     * by the cashier endpoint after bank-transfer reconciliation.
     * The caller owns the transaction; this service is the single authoritative
     * final-sale path for both MANUAL and AUTO completion.
     */
    public function completePaidOrder(
        int $orderId,
        User $actor,
        ?string $requestId = null,
        string $source = 'POS',
    ): Order {
        $order = $this->orders->findByIdForUpdate($orderId);
        if ($order === null) {
            throw new \RuntimeException('Order not found.');
        }

        if ($order->isCompleted()) {
            return $order;
        }

        if (!$order->isDraft()) {
            throw new \DomainException('Only draft orders can be completed.');
        }

        if (!$order->getPaidAmount()->equals($order->getTotal()) || !$order->getDebtAmount()->isZero()) {
            throw new \DomainException('Order cannot be completed until it is fully paid.');
        }

        foreach ($order->getPayments() as $payment) {
            if ($payment->getMethod() !== PaymentMethod::BANK_TRANSFER || $payment->getReference() === null || $this->paymentReferences === null) {
                continue;
            }
            $reference = $this->paymentReferences->findByReference($payment->getReference());
            if ($reference === null || $reference->getStatus() !== PaymentReferenceStatus::MATCHED || $reference->getOrder() !== $order) {
                throw new \DomainException('Bank transfer payment reference has not been reconciled.');
            }
        }

        $products = $this->lockProducts($order);

        foreach ($products as $productId => $product) {
            $quantity = $this->quantityForProduct($order, $productId);
            if ($product->getStockQuantity() < $quantity) {
                throw new \DomainException(sprintf('Insufficient stock for product %d.', $productId));
            }

            $before = $product->getStockQuantity();
            $product->decreaseStock($quantity);

            $this->stockMovements->save(new StockMovement(
                product: $product,
                type: StockMovementType::SALE,
                quantityBefore: $before,
                quantityChange: -$quantity,
                user: $actor,
                order: $order,
                session: null,
                reason: $source === 'BANK_WEBHOOK_AUTO' ? 'Bank transfer payment auto completion' : 'POS Complete sale',
            ));
        }

        $oldStatus = $order->getStatus()->value;
        $order->complete();

        $this->auditLogs->save(new AuditLog(
            action: 'ORDER_COMPLETED',
            user: $actor,
            session: null,
            entityType: 'Order',
            entityId: (string) $order->getId(),
            oldValues: [
                'status' => $oldStatus,
            ],
            newValues: [
                'status' => $order->getStatus()->value,
                'total' => $order->getTotal()->toDecimal(),
                'paidAmount' => $order->getPaidAmount()->toDecimal(),
                'debtAmount' => $order->getDebtAmount()->toDecimal(),
                'source' => $source,
                'requestId' => $requestId,
            ],
        ));

        $this->orders->save($order);
        return $order;
    }

    /** @return array<int, \App\Domain\Product\Product> */
    private function lockProducts(Order $order): array
    {
        $ids = [];
        foreach ($order->getItems() as $item) {
            $productId = $item->getProduct()->getId();
            if ($productId === null) {
                throw new \LogicException('Order item product has no ID.');
            }
            $ids[$productId] = true;
        }

        $productIds = array_keys($ids);
        sort($productIds, SORT_NUMERIC);

        $products = [];
        foreach ($productIds as $productId) {
            $products[$productId] = $this->productLocking->lock($productId);
        }

        return $products;
    }

    private function quantityForProduct(Order $order, int $productId): int
    {
        $quantity = 0;
        foreach ($order->getItems() as $item) {
            if ($item->getProduct()->getId() === $productId) {
                $quantity += $item->getQuantity();
            }
        }

        return $quantity;
    }
}
