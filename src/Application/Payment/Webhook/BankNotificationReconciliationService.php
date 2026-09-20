<?php

declare(strict_types=1);

namespace App\Application\Payment\Webhook;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Order\Command\Checkout\ProductLockingInterface;
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
use App\Domain\Payment\ExternalPaymentTransaction;
use App\Domain\Payment\Repository\CheckoutPaymentSessionRepositoryInterface;
use App\Domain\Payment\Repository\ExternalPaymentTransactionRepositoryInterface;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BankNotificationReconciliationService
{
    public function __construct(
        private PaymentReferenceRepositoryInterface $references,
        private CheckoutPaymentSessionRepositoryInterface $sessions,
        private ExternalPaymentTransactionRepositoryInterface $transactions,
        private PaymentRepositoryInterface $payments,
        private OrderRepositoryInterface $orders,
        private OrderNumberGeneratorInterface $orderNumberGenerator,
        private ProductLockingInterface $productLocking,
        private AuditLogRepositoryInterface $auditLogs,
        private EntityManagerInterface $em,
        private TransactionManagerInterface $transactionManager,
    ) {}

    /** @return array{status:string,transactionId:int|null,paymentId:int|null,reference:string|null,orderId:int|null} */
    public function reconcile(
        string $provider,
        string $externalId,
        string $description,
        \DateTimeImmutable $occurredAt,
        string $reference,
        ?string $amount = null,
        ?int $bankAccountId = null,
    ): array {
        return $this->transactionManager->run(function () use ($provider, $externalId, $description, $occurredAt, $reference, $amount, $bankAccountId): array {
            $existing = $this->transactions->findByProviderTransactionId($provider, $externalId);
            if ($existing !== null) {
                $this->assertDuplicateIsConsistent(
                    existing: $existing,
                    reference: $reference,
                    amount: $amount,
                    bankAccountId: $bankAccountId,
                );

                return $this->duplicateResult($existing);
            }

            $paymentReference = $this->references->findByReferenceForUpdate($reference);
            if ($paymentReference === null) {
                throw new \InvalidArgumentException('Payment reference not found.');
            }

            if ($paymentReference->getStatus() === PaymentReferenceStatus::MATCHED) {
                // Manual bank confirmation may have matched the reference before
                // the provider webhook arrived. The webhook is then an enrichment
                // event: keep the existing payment/order and persist the missing
                // external transaction exactly once. Never reject it because the
                // original reference is now past its expiry.
                $session = $paymentReference->getCheckoutPaymentSession();
                $payment = $paymentReference->getPayment();
                $order = $paymentReference->getOrder();
                if ($payment === null || $order === null) {
                    throw new \LogicException('Matched payment reference is missing its payment or order.');
                }

                if ($amount !== null && !Money::fromDecimal($amount)->equals($payment->getAmount())) {
                    throw new \DomainException('Bank notification amount does not match the matched payment.');
                }
                $account = $paymentReference->getBankAccount();
                if ($bankAccountId !== null && $account->getId() !== null && $bankAccountId !== $account->getId()) {
                    throw new \DomainException('Bank notification bank account does not match the matched payment.');
                }

                $transaction = new ExternalPaymentTransaction(
                    provider: $provider,
                    externalTransactionId: $externalId,
                    account: $account,
                    amount: $payment->getAmount(),
                    description: $description,
                    occurredAt: $occurredAt,
                    transactionReference: $paymentReference->getReference(),
                    status: 'MATCHED',
                );
                $transaction->matchPayment($payment, 'MATCHED');
                $this->transactions->save($transaction);
                $this->auditLogs->save(new AuditLog(
                    action: 'PAYMENT_RECONCILED_ENRICHED',
                    user: $session->getUser(),
                    session: null,
                    entityType: 'PaymentReference',
                    entityId: (string) $paymentReference->getId(),
                    newValues: [
                        'orderId' => $order->getId(),
                        'paymentId' => $payment->getId(),
                        'paymentReference' => $paymentReference->getReference(),
                        'provider' => $provider,
                        'externalTransactionId' => $externalId,
                    ],
                ));
                $this->em->flush();

                return [
                    'status' => 'ENRICHED',
                    'transactionId' => $transaction->getId(),
                    'paymentId' => $payment->getId(),
                    'reference' => $paymentReference->getReference(),
                    'orderId' => $order->getId(),
                ];
            }

            $session = $this->sessions->findByIdForUpdate($paymentReference->getCheckoutPaymentSession()->getId() ?? 0);
            if ($session === null) {
                throw new \LogicException('Checkout payment session not found.');
            }

            if ($paymentReference->getStatus() !== PaymentReferenceStatus::PENDING) {
                throw new \DomainException('Payment reference is no longer valid.');
            }
            // Webhook validity is governed by the payment reference lifecycle,
            // not by the UI/session lifecycle. A delayed webhook may legitimately
            // arrive after the checkout session has expired, provided the bank
            // transaction itself occurred before the reference expiry.
            if ($paymentReference->isExpired($occurredAt)) {
                $paymentReference->expire($occurredAt);
                $this->em->flush();
                throw new \DomainException('Payment reference has expired.');
            }

            $expectedAmount = $paymentReference->getAmount();
            if (!$session->getAmount()->equals($expectedAmount)) {
                throw new \DomainException('Payment session amount does not match the payment reference amount.');
            }
            $account = $paymentReference->getBankAccount();
            if ($bankAccountId !== null && $account->getId() !== null && $bankAccountId !== $account->getId()) {
                throw new \DomainException('Bank notification bank account does not match the payment reference account.');
            }
            if ($amount !== null && !Money::fromDecimal($amount)->equals($expectedAmount)) {
                throw new \DomainException('Bank notification amount does not match the payment reference amount.');
            }

            $order = $session->getOrder();
            if ($order === null) {
                $order = $this->createOrderFromSnapshot($session);
                $this->orders->save($order);
                $session->attachOrder($order);
            } else {
                $order = $this->orders->findByIdForUpdate($order->getId() ?? 0) ?? $order;
            }

            if (!$order->isDraft()) {
                throw new \DomainException('Order is no longer awaiting bank payment.');
            }
            if (!$order->getTotal()->equals($expectedAmount)) {
                throw new \DomainException('Order amount does not match the payment reference amount.');
            }

            $payment = new Payment(
                amount: $expectedAmount,
                method: PaymentMethod::BANK_TRANSFER,
                user: $session->getUser(),
                reference: $paymentReference->getReference(),
                bankAccount: $account,
            );
            $order->addPayment($payment);
            $this->payments->save($payment);

            $paymentReference->attachOrder($order);
            $paymentReference->attachPayment($payment);
            $paymentReference->markMatched($occurredAt);
            $session->markPaid();

            $transaction = new ExternalPaymentTransaction(
                provider: $provider,
                externalTransactionId: $externalId,
                account: $account,
                amount: $expectedAmount,
                description: $description,
                occurredAt: $occurredAt,
                transactionReference: $paymentReference->getReference(),
                status: 'MATCHED',
            );
            $transaction->matchPayment($payment, 'MATCHED');
            $this->transactions->save($transaction);

            $this->auditLogs->save(new AuditLog(
                action: 'PAYMENT_RECONCILED',
                user: $session->getUser(),
                session: null,
                entityType: 'PaymentReference',
                entityId: (string) $paymentReference->getId(),
                newValues: [
                    'orderId' => $order->getId(),
                    'paymentId' => $payment->getId(),
                    'paymentReference' => $paymentReference->getReference(),
                    'paymentMethod' => PaymentMethod::BANK_TRANSFER->value,
                    'completionPolicy' => 'MANUAL',
                ],
            ));

            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
                $existing = $this->transactions->findByProviderTransactionId($provider, $externalId);
                if ($existing !== null) return $this->duplicateResult($existing);
                throw new \RuntimeException('Bank notification idempotency conflict.');
            }

            return [
                'status' => $transaction->getStatus(),
                'transactionId' => $transaction->getId(),
                'paymentId' => $payment->getId(),
                'reference' => $paymentReference->getReference(),
                'orderId' => $order->getId(),
            ];
        });
    }

    private function createOrderFromSnapshot(\App\Domain\Payment\CheckoutPaymentSession $session): Order
    {
        $order = new Order(
            orderNumber: $this->orderNumberGenerator->generate(),
            user: $session->getUser(),
            customer: $session->getCustomer(),
            note: $session->getNote(),
        );

        foreach ($session->getCartSnapshot() as $item) {
            $product = $this->productLocking->lock((int) $item['productId']);
            $quantity = (int) $item['quantity'];
            $snapshotPrice = Money::fromDecimal((string) $item['unitPrice']);
            $order->addItem(new OrderItem($product, $quantity, $snapshotPrice));
        }
        $order->recalculateTotals();
        return $order;
    }

    private function assertDuplicateIsConsistent(
        ExternalPaymentTransaction $existing,
        string $reference,
        ?string $amount,
        ?int $bankAccountId,
    ): void {
        $existingReference = $existing->getTransactionReference();
        if ($existingReference !== null && strtoupper($existingReference) !== strtoupper($reference)) {
            throw new \DomainException('Bank notification conflicts with existing external transaction reference.');
        }

        if ($amount !== null && !Money::fromDecimal($amount)->equals($existing->getAmount())) {
            throw new \DomainException('Bank notification conflicts with existing external transaction amount.');
        }

        if ($bankAccountId !== null && $existing->getPaymentBankAccount()->getId() !== null && $bankAccountId !== $existing->getPaymentBankAccount()->getId()) {
            throw new \DomainException('Bank notification conflicts with existing external transaction bank account.');
        }
    }

    private function duplicateResult(ExternalPaymentTransaction $existing): array
    {
        return [
            'status' => 'DUPLICATE',
            'transactionId' => $existing->getId(),
            'paymentId' => $existing->getMatchedPayment()?->getId(),
            'reference' => $existing->getTransactionReference(),
            'orderId' => $existing->getMatchedOrder()?->getId(),
        ];
    }
}
