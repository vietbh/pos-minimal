<?php

declare(strict_types=1);

namespace App\Application\Order\Query\CopyOrderToPos;

use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Order\Order;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Payment\Enum\PaymentMethod;

final readonly class CopyOrderToPosHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private AuditLogRepositoryInterface $auditLogs,
    ) {
    }

    public function __invoke(CopyOrderToPosInput $input): ?CopyOrderToPosResult
    {
        if ($input->orderId <= 0) {
            throw new \InvalidArgumentException('Order ID must be greater than zero.');
        }

        $order = $this->orders->findById($input->orderId);
        if (!$order instanceof Order || $order->getId() === null) {
            return null;
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $productId = $product->getId();
            if ($productId === null) {
                throw new \LogicException('Order contains an item with an invalid product.');
            }

            // The new POS draft must use the product's current authoritative
            // price. The historical order remains unchanged and is never edited.
            $items[] = new CopyOrderToPosItemResult(
                productId: $productId,
                name: $product->getName(),
                sku: $product->getSku()?->value(),
                unitPrice: $product->getSellingPrice()->toDecimal(),
                quantity: $item->getQuantity(),
                active: $product->isActive(),
            );
        }

        return new CopyOrderToPosResult(
            sourceOrderId: $order->getId(),
            sourceOrderNumber: $order->getOrderNumber()->value(),
            customerId: $order->getCustomer()?->getId(),
            customerName: $order->getCustomer()?->getName(),
            customerPhone: $order->getCustomer()?->getPhone(),
            customerDefaultDiscountPercent: $order->getCustomer()?->getDefaultDiscountPercent() ?? 0,
            cashTenderedAmount: $this->resolveCashTenderedAmount($order),
            items: $items,
        );
    }

    private function resolveCashTenderedAmount(Order $order): ?string
    {
        $hasCashPayment = false;
        $fallbackPaidAmount = null;

        foreach ($order->getPayments() as $payment) {
            if ($payment->getMethod() !== PaymentMethod::CASH) {
                continue;
            }

            $hasCashPayment = true;
            $fallbackPaidAmount = $payment->getAmount()->toDecimal();
            break;
        }

        if (!$hasCashPayment || $order->getId() === null) {
            return null;
        }

        // Checkout already records the exact cash tendered amount in the
        // immutable ORDER_COMPLETED audit snapshot. Prefer it when available.
        foreach ($this->auditLogs->findByEntity('Order', (string) $order->getId()) as $audit) {
            if ($audit->getAction() !== 'ORDER_COMPLETED') {
                continue;
            }

            $values = $audit->getNewValues() ?? [];
            $tendered = $values['tenderedAmount'] ?? null;
            if (is_string($tendered) || is_int($tendered) || is_float($tendered)) {
                return (string) $tendered;
            }
        }

        // Older orders may not have the tendered snapshot. In that case the
        // recorded cash payment is the safest available value to prefill.
        return $fallbackPaidAmount;
    }
}
