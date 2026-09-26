<?php

declare(strict_types=1);

namespace App\Application\Payment;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Payment\Reference\PaymentReferenceNormalizer;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Order\Command\Checkout\ProductLockingInterface;
use App\Application\Order\Command\CompleteOrder\CompleteOrderService;
use App\Application\Order\OrderNumberGeneratorInterface;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\Repository\PaymentRepositoryInterface;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Payment\Enum\PaymentReferenceStatus;
use App\Domain\Payment\Repository\CheckoutPaymentSessionRepositoryInterface;
use App\Domain\Payment\Repository\ExternalPaymentTransactionRepositoryInterface;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;

final readonly class ManualBankPaymentConfirmationService
{
    public function __construct(
        private CheckoutPaymentSessionRepositoryInterface $sessions,
        private PaymentReferenceRepositoryInterface $references,
        private PaymentRepositoryInterface $payments,
        private OrderRepositoryInterface $orders,
        private OrderNumberGeneratorInterface $orderNumberGenerator,
        private ProductLockingInterface $productLocking,
        private AuditLogRepositoryInterface $auditLogs,
        private CompleteOrderService $completion,
        private ExternalPaymentTransactionRepositoryInterface $externalTransactions,
        private TransactionManagerInterface $transactions,
        private ?PaymentReferenceNormalizer $referenceNormalizer = null,
    ) {}

    /**
     * Cashier asserts that they have independently verified the bank transfer.
     * This deliberately does not fabricate provider metadata; a later webhook
     * enriches the matched payment with the real external transaction.
     *
     * @return array{status:string,sessionId:int,orderId:int,orderNumber:string,paymentId:int,reference:string,total:string,paidAmount:string,debtAmount:string,webhookEnrichmentPending:bool,externalTransaction:?array}
     */
    public function confirmAndComplete(
        int $sessionId,
        string $reference,
        string $amount,
        User $actor,
        ?string $requestId = null,
    ): array {
        return $this->transactions->run(function (TransactionContextInterface $transaction) use ($sessionId, $reference, $amount, $actor, $requestId): array {
            $session = $this->sessions->findByIdForUpdate($sessionId);
            if ($session === null) {
                throw new \RuntimeException('Checkout payment session not found.');
            }
            if ($session->getStatus()->value !== 'WAITING_FOR_BANK_PAYMENT') {
                if ($session->getStatus()->value === 'PAID' && $session->getOrder()?->isCompleted()) {
                    $order = $session->getOrder();
                    $payment = $order?->getPayments()->last();
                    if ($order !== null && $payment !== false && $payment !== null) {
                        return $this->result($session, $order, $payment);
                    }
                }
                throw new \DomainException('Payment session is no longer awaiting manual confirmation.');
            }

            $reference = ($this->referenceNormalizer ?? new PaymentReferenceNormalizer())->normalize($reference);
            $paymentReference = $this->references->findByReferenceForUpdate($reference);
            if ($paymentReference === null || $paymentReference->getCheckoutPaymentSession() !== $session) {
                throw new \DomainException('Payment reference does not belong to this payment session.');
            }
            if ($paymentReference->getStatus() !== PaymentReferenceStatus::PENDING) {
                throw new \DomainException('Payment reference is no longer pending.');
            }
            if ($paymentReference->isExpired()) {
                throw new \DomainException('Payment reference has expired.');
            }

            $money = Money::fromDecimal($amount);
            if (!$money->equals($paymentReference->getAmount()) || !$money->equals($session->getAmount())) {
                throw new \DomainException('Manual bank confirmation amount does not match the payment session.');
            }

            $order = $session->getOrder();
            if ($order === null) {
                $order = $this->createOrderFromSnapshot($session);
                $this->orders->save($order);
                $session->attachOrder($order);
            } else {
                $order = $this->orders->findByIdForUpdate($order->getId() ?? 0) ?? $order;
            }

            if (!$order->isDraft() || !$order->getTotal()->equals($money)) {
                throw new \DomainException('Order is not in a valid paid draft state.');
            }

            $payment = new Payment(
                amount: $money,
                method: PaymentMethod::BANK_TRANSFER,
                user: $session->getUser(),
                reference: $paymentReference->getReference(),
                bankAccount: $paymentReference->getBankAccount(),
            );
            $order->addPayment($payment);
            $this->payments->save($payment);
            $paymentReference->attachOrder($order);
            $paymentReference->attachPayment($payment);
            $paymentReference->markMatched(new \DateTimeImmutable());
            $session->markPaid();

            // The order/payment use generated database identifiers. Synchronize the
            // pending inserts while keeping the current transaction open before the
            // completion service needs the order ID and reloads it with FOR UPDATE.
            $transaction->flush();

            $this->auditLogs->save(new AuditLog(
                action: 'PAYMENT_MANUALLY_CONFIRMED',
                user: $actor,
                session: null,
                entityType: 'PaymentReference',
                entityId: (string) $paymentReference->getId(),
                newValues: [
                    'orderId' => $order->getId(),
                    'paymentId' => $payment->getId(),
                    'paymentReference' => $paymentReference->getReference(),
                    'amount' => $money->toDecimal(),
                    'source' => 'CASHIER_MANUAL_BANK_CONFIRMATION',
                    'requestId' => $requestId,
                    'webhookEnrichmentPending' => true,
                ],
            ));

            $this->completion->completePaidOrder(
                orderId: $order->getId() ?? throw new \LogicException('Order ID is missing.'),
                actor: $actor,
                requestId: $requestId,
                source: 'MANUAL_BANK_CONFIRMATION',
            );
            return $this->result($session, $order, $payment);
        });
    }

    private function createOrderFromSnapshot(\App\Domain\Payment\CheckoutPaymentSession $session): Order
    {
        $order = new Order(
            orderNumber: $this->orderNumberGenerator->generate(),
            user: $session->getUser(),
            customer: $session->getCustomer(),
            note: $session->getNote(),
            salesPoint: $session->getSalesPoint(),
        );
        $order->setDiscountPercent($session->getDiscountPercent());
        $order->setManualDiscount($session->getManualDiscount());
        foreach ($session->getCartSnapshot() as $item) {
            $product = $this->productLocking->lock((int) $item['productId']);
            $order->addItem(new OrderItem(
                $product,
                (int) $item['quantity'],
                Money::fromDecimal((string) $item['unitPrice']),
            ));
        }
        $order->recalculateTotals();
        return $order;
    }

    private function result(\App\Domain\Payment\CheckoutPaymentSession $session, Order $order, Payment $payment): array
    {
        return [
            'status' => $order->getStatus()->value,
            'sessionId' => $session->getId() ?? throw new \LogicException('Session ID is missing.'),
            'orderId' => $order->getId() ?? throw new \LogicException('Order ID is missing.'),
            'orderNumber' => $order->getOrderNumber()->value(),
            'paymentId' => $payment->getId() ?? throw new \LogicException('Payment ID is missing.'),
            'reference' => $payment->getReference() ?? throw new \LogicException('Payment reference is missing.'),
            'total' => $order->getTotal()->toDecimal(),
            'paidAmount' => $order->getPaidAmount()->toDecimal(),
            'debtAmount' => $order->getDebtAmount()->toDecimal(),
            'webhookEnrichmentPending' => ($externalTransaction = $this->externalTransactions->findLatestByOrderId($order->getId() ?? 0)) === null,
            'externalTransaction' => $externalTransaction === null ? null : [
                'id' => $externalTransaction->getId(),
                'provider' => $externalTransaction->getProvider(),
                'externalTransactionId' => $externalTransaction->getExternalTransactionId(),
                'amount' => $externalTransaction->getAmount()->toDecimal(),
                'description' => $externalTransaction->getDescription(),
                'reference' => $externalTransaction->getTransactionReference(),
                'occurredAt' => $externalTransaction->getOccurredAt()->format(\DateTimeInterface::ATOM),
                'status' => $externalTransaction->getStatus(),
            ],
        ];
    }
}
