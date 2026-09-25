<?php

declare(strict_types=1);

namespace App\Application\Order\Command\Checkout;

use App\Application\Common\Idempotency\IdempotencyDecision;
use App\Application\Common\Idempotency\IdempotencyDecisionType;
use App\Application\Common\Idempotency\IdempotencyPort;
use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Order\OrderNumberGeneratorInterface;
use App\Application\Security\ActorContext;
use App\Application\Security\ActorContextProviderInterface;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Customer\Customer;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use App\Domain\Debt\Debt;
use App\Domain\Debt\Repository\DebtRepositoryInterface;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\Repository\PaymentRepositoryInterface;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Payment\PaymentBankAccount;
use App\Domain\Payment\Repository\PaymentBankAccountRepositoryInterface;
use App\Domain\SalesPoint\SalesPoint;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Application\Payment\Reference\CheckoutPaymentSessionService;
use App\Application\Payment\Enum\BankTransferCompletionPolicy;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\Stock\StockMovement;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserSession;

final readonly class CheckoutHandler
{
    private const OPERATION = 'checkout';
    private const RESPONSE_STATUS_OK = 200;

    public function __construct(
        private ActorContextProviderInterface $actorContextProvider,
        private TransactionManagerInterface $transactionManager,
        private IdempotencyPort $idempotency,
        private ProductLockingInterface $productLocking,
        private OrderNumberGeneratorInterface $orderNumberGenerator,
        private UserRepositoryInterface $userRepository,
        private UserSessionRepositoryInterface $userSessionRepository,
        private CustomerRepositoryInterface $customerRepository,
        private OrderRepositoryInterface $orderRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentBankAccountRepositoryInterface $paymentBankAccountRepository,
        private SalesPointRepositoryInterface $salesPointRepository,
        private CheckoutPaymentSessionService $checkoutPaymentSessionService,
        private string $bankTransferCompletionPolicy,
        private DebtRepositoryInterface $debtRepository,
        private StockMovementRepositoryInterface $stockMovementRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws \JsonException
     */
    public function __invoke(CheckoutInput $input): CheckoutResult
    {
        $this->validateInput($input);

        $actorContext = $this->actorContextProvider->get();

        $requestFingerprint = $this->buildRequestFingerprint($input);

        /*
         * Transaction #1
         *
         * Reserve the idempotency key and commit PROCESSING
         * before entering the business transaction.
         */
        $decision = $this->transactionManager->run(
            function () use (
                $actorContext,
                $input,
                $requestFingerprint,
            ): IdempotencyDecision {
                return $this->idempotency->start(
                    userId: $actorContext->userId,
                    operation: self::OPERATION,
                    idempotencyKey: $input->idempotencyKey,
                    requestFingerprint: $requestFingerprint,
                );
            },
        );

        return match ($decision->type) {
            IdempotencyDecisionType::REPLAY
            => $this->replay($decision),

            IdempotencyDecisionType::IN_PROGRESS
            => throw new \DomainException(
                'Checkout with this idempotency key is already in progress.',
            ),

            IdempotencyDecisionType::EXECUTE
            => $this->executeWithIdempotencyOutcome(
                $input,
                $decision,
                $actorContext,
            ),
        };
    }

    private function executeWithIdempotencyOutcome(
        CheckoutInput $input,
        IdempotencyDecision $decision,
        ActorContext $actorContext,
    ): CheckoutResult {
        try {
            /*
             * Transaction #2
             *
             * ALL business mutations live inside this transaction.
             *
             * If anything fails, this transaction is rolled back.
             */
            $result = $this->transactionManager->run(
                function (
                    TransactionContextInterface $transaction,
                ) use (
                    $input,
                    $decision,
                    $actorContext,
                ): CheckoutResult {
                    return $this->execute(
                        $input,
                        $decision,
                        $transaction,
                        $actorContext,
                    );
                },
            );
        } catch (\Throwable $exception) {
            // A different idempotency key may concurrently create the same
            // active bank-payment session. The DB unique key is authoritative;
            // recover its committed session instead of creating a duplicate.
            if ($input->payment->method === PaymentMethod::BANK_TRANSFER
                && $exception instanceof \Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                $recovered = $this->transactionManager->run(
                    fn (): ?CheckoutResult => $this->recoverConcurrentBankSession($input),
                );
                if ($recovered !== null) {
                    $result = $recovered;
                } else {
                    $this->transactionManager->run(fn () => $this->idempotency->fail($decision, $this->resolveFailureStatus($exception), ['error' => $exception->getMessage()]));
                    throw $exception;
                }
            } else {
                $this->transactionManager->run(fn () => $this->idempotency->fail($decision, $this->resolveFailureStatus($exception), ['error' => $exception->getMessage()]));
                throw $exception;
            }
        }

        /*
         * Transaction #3
         *
         * Business transaction has already committed.
         *
         * Persist PROCESSING -> COMPLETED independently.
         */
        $this->transactionManager->run(
            function () use (
                $decision,
                $result,
            ): void {
                $this->idempotency->complete(
                    decision: $decision,
                    responseStatus: self::RESPONSE_STATUS_OK,
                    responseBody: $this->serializeResult($result),
                );
            },
        );

        return $result;
    }

    private function recoverConcurrentBankSession(CheckoutInput $input): ?CheckoutResult
    {
        $result = $this->checkoutPaymentSessionService->reuseActive($this->buildRequestFingerprint($input));
        if ($result === null) return null;
        $total = Money::fromDecimal($result['amount']);
        return new CheckoutResult(
            orderId: $result['orderId'],
            orderNumber: null,
            total: $total,
            paidAmount: Money::zero(),
            debtAmount: $total,
            tenderedAmount: Money::zero(),
            changeAmount: Money::zero(),
            status: null,
            paymentReference: $result['reference'],
            paymentReferenceExpiresAt: $result['expiresAt'],
            paymentReferenceTransferContent: $result['transferContent'],
            paymentReferenceQrUrl: $result['qrUrl'],
            bankTransferCompletionPolicy: BankTransferCompletionPolicy::MANUAL->value,
            paymentSessionId: $result['sessionId'],
        );
    }

    private function execute(
        CheckoutInput $input,
        IdempotencyDecision $decision,
        TransactionContextInterface $transaction,
        ActorContext $actorContext,
    ): CheckoutResult {
        $completionPolicy = BankTransferCompletionPolicy::tryFrom(strtoupper(trim($this->bankTransferCompletionPolicy)));
        if ($completionPolicy === null) {
            throw new \LogicException('Invalid bank transfer completion policy.');
        }

        $user = $this->resolveUser($actorContext);
        $session = $this->resolveSession($actorContext);
        $customer = $this->resolveCustomer($input->customerId);
        $bankAccount = $this->resolveBankAccount($input);
        $salesPoint = $this->resolveSalesPoint($input->salesPointId);
        $isBankTransfer = $input->payment->method === PaymentMethod::BANK_TRANSFER;
        $paymentReferenceResult = null;

        $quantities = $this->aggregateQuantities(
            $input->items,
        );

        $productIds = array_keys($quantities);
        sort($productIds, SORT_NUMERIC);

        /** @var array<int, Product> $products */
        $products = [];

        foreach ($productIds as $productId) {
            $products[$productId] = $this->productLocking->lock(
                $productId,
            );
        }

        if ($isBankTransfer) {
            if ($bankAccount === null) {
                throw new \DomainException('Receiving bank account is required for bank transfer.');
            }

            $snapshot = [];
            $calculatedTotal = Money::zero();
            foreach ($productIds as $productId) {
                $product = $products[$productId];
                $quantity = $quantities[$productId];
                $this->assertProductCanBeSold($product, $quantity);
                $unitPrice = $product->getSellingPrice();
                $snapshot[] = [
                    'productId' => $productId,
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice->toDecimal(),
                ];
                $calculatedTotal = $calculatedTotal->add($unitPrice->multiply($quantity));
            }

            if (!$input->payment->amount->equals($calculatedTotal)) {
                throw new \DomainException('Bank transfer amount must equal the order total.');
            }

            $sessionResult = $this->checkoutPaymentSessionService->createOrReuse(
                user: $user,
                customer: $customer,
                bankAccount: $bankAccount,
                snapshot: $snapshot,
                amount: $calculatedTotal,
                note: $input->note,
                activeKey: $this->buildRequestFingerprint($input),
                salesPoint: $salesPoint,
            );

            return new CheckoutResult(
                orderId: $sessionResult['orderId'],
                orderNumber: null,
                total: $calculatedTotal,
                paidAmount: Money::zero(),
                debtAmount: $calculatedTotal,
                tenderedAmount: Money::zero(),
                changeAmount: Money::zero(),
                status: null,
                paymentReference: $sessionResult['reference'],
                paymentReferenceExpiresAt: $sessionResult['expiresAt'],
                paymentReferenceTransferContent: $sessionResult['transferContent'],
                paymentReferenceQrUrl: $sessionResult['qrUrl'],
                bankTransferCompletionPolicy: BankTransferCompletionPolicy::MANUAL->value,
                paymentSessionId: $sessionResult['sessionId'],
            );
        }

        $order = new Order(
            orderNumber: $this->generateOrderNumber(),
            user: $user,
            customer: $customer,
            note: $input->note,
            salesPoint: $salesPoint,
        );

        foreach ($productIds as $productId) {
            $product = $products[$productId];
            $quantity = $quantities[$productId];
            $this->assertProductCanBeSold($product, $quantity);
            $order->addItem(new OrderItem(
                product: $product,
                quantity: $quantity,
                unitPrice: $product->getSellingPrice(),
            ));
        }
        $order->recalculateTotals();

        $paymentAmount = $input->payment->amount;
        $tenderedAmount = $input->payment->tenderedAmount ?? $paymentAmount;
        if ($tenderedAmount->isLessThanOrEqual($order->getTotal())) {
            $paymentAmount = $tenderedAmount;
        } else {
            $paymentAmount = $order->getTotal();
        }

        if ($paymentAmount->isPositive()) {
            $payment = new Payment(
                amount: $paymentAmount,
                method: $input->payment->method,
                user: $user,
                reference: $input->paymentReference,
                bankAccount: $bankAccount,
            );
            $order->addPayment($payment);
            $this->paymentRepository->save($payment);
        }

        // A cash checkout may legitimately have tenderedAmount = 0 when the
        // customer is buying on credit. The debt/customer invariant is
        // enforced below, while the order must still become COMPLETED.
        $order->complete();

        $this->orderRepository->save($order);
        $transaction->flush();

        $orderId = $order->getId();
        if ($orderId === null) {
            throw new \LogicException('Order ID was not generated after transaction flush.');
        }

        /*
         * Stock mutation occurs only after every product has been
         * locked and validated.
         */
        foreach ($productIds as $productId) {
            $product = $products[$productId];
            $quantity = $quantities[$productId];

            $quantityBefore = $product->getStockQuantity();

            $product->decreaseStock($quantity);

            $movement = new StockMovement(
                product: $product,
                type: StockMovementType::SALE,
                quantityBefore: $quantityBefore,
                quantityChange: -$quantity,
                user: $user,
                order: $order,
                session: $session,
                reason: 'Checkout',
            );

            $this->stockMovementRepository->save($movement);
        }

        $debtAmount = $order->getDebtAmount();

        if ($debtAmount->isPositive()) {
            if ($customer === null) {
                throw new \DomainException(
                    'Customer is required when checkout creates debt.',
                );
            }

            $this->debtRepository->save(
                new Debt(
                    customer: $customer,
                    order: $order,
                    createdBy: $user,
                    originalAmount: $debtAmount,
                ),
            );
        }

        $this->auditLogRepository->save(
            new AuditLog(
                action: 'ORDER_COMPLETED',
                user: $user,
                session: $session,
                entityType: 'Order',
                entityId: (string) $orderId,
                newValues: [
                    'orderNumber' => $order->getOrderNumber()->value(),
                    'status' => $order->getStatus()->value,
                    'total' => $order->getTotal()->toDecimal(),
                    'paidAmount' => $order->getPaidAmount()->toDecimal(),
                    'debtAmount' => $order->getDebtAmount()->toDecimal(),
                    'tenderedAmount' => $tenderedAmount->toDecimal(),
                    'changeAmount' => $this->calculateChange(
                        $input,
                        $order->getTotal(),
                        $tenderedAmount,
                    )->toDecimal(),
                ],
            ),
        );

        /*
         * IMPORTANT:
         *
         * Do NOT call idempotency->complete() here.
         *
         * Completion is persisted only after Transaction #2
         * successfully commits.
         */
        return new CheckoutResult(
            orderId: $orderId,
            orderNumber: $order
                ->getOrderNumber()
                ->value(),
            total: $order->getTotal(),
            paidAmount: $order->getPaidAmount(),
            debtAmount: $order->getDebtAmount(),
            tenderedAmount: $tenderedAmount,
            changeAmount: $this->calculateChange(
                $input,
                $order->getTotal(),
                $tenderedAmount,
            ),
            status: $order->getStatus(),
            paymentReference: $paymentReferenceResult['reference'] ?? null,
            paymentReferenceExpiresAt: $paymentReferenceResult['expiresAt'] ?? null,
        );
    }

    private function resolveUser(
        ActorContext $actorContext,
    ): User {
        $user = $this->userRepository->findById(
            $actorContext->userId,
        );

        if ($user === null) {
            throw new \DomainException(
                'Authenticated user was not found.',
            );
        }

        if (!$user->isActive()) {
            throw new \DomainException(
                'Authenticated user is inactive.',
            );
        }

        return $user;
    }

    private function resolveSession(
        ActorContext $actorContext,
    ): ?UserSession {
        if ($actorContext->sessionId === null) {
            return null;
        }

        return $this->userSessionRepository->findBySessionIdentifier(
            $actorContext->sessionId,
        );
    }

    private function resolveSalesPoint(?int $salesPointId): ?SalesPoint
    {
        if ($salesPointId === null) {
            return null;
        }
        $salesPoint = $this->salesPointRepository->findById($salesPointId);
        if ($salesPoint === null || !$salesPoint->isActive()) {
            throw new \DomainException('Sales point is not available.');
        }
        return $salesPoint;
    }

    private function resolveCustomer(
        ?int $customerId,
    ): ?Customer {
        if ($customerId === null) {
            return null;
        }

        $customer = $this->customerRepository->findById(
            $customerId,
        );

        if ($customer === null) {
            throw new \DomainException(
                sprintf(
                    'Customer %d was not found.',
                    $customerId,
                ),
            );
        }

        return $customer;
    }

    /**
     * @param list<CheckoutItemInput> $items
     *
     * @return array<int, int>
     */
    private function aggregateQuantities(
        array $items,
    ): array {
        $quantities = [];

        foreach ($items as $item) {
            $productId = $item->productId;
            $quantity = $item->quantity;

            if ($productId <= 0) {
                throw new \InvalidArgumentException(
                    'Product ID must be greater than zero.',
                );
            }

            if ($quantity <= 0) {
                throw new \InvalidArgumentException(
                    'Product quantity must be greater than zero.',
                );
            }

            $quantities[$productId] =
                ($quantities[$productId] ?? 0) + $quantity;
        }

        ksort($quantities, SORT_NUMERIC);

        return $quantities;
    }

    private function assertProductCanBeSold(
        Product $product,
        int $quantity,
    ): void {
        if (!$product->isActive()) {
            throw new \DomainException(
                sprintf(
                    'Product %d is inactive.',
                    $product->getId(),
                ),
            );
        }

        if ($product->getStockQuantity() < $quantity) {
            throw new \DomainException(
                sprintf(
                    'Insufficient stock for product %d.',
                    $product->getId(),
                ),
            );
        }
    }

    private function generateOrderNumber(): OrderNumber
    {
        return $this->orderNumberGenerator->generate();
    }

    private function validateInput(
        CheckoutInput $input,
    ): void {
        if ($input->items === []) {
            throw new \InvalidArgumentException(
                'Checkout must contain at least one item.',
            );
        }

        if ($input->payment->method === PaymentMethod::BANK_TRANSFER && $input->bankAccountId === null) {
            throw new \InvalidArgumentException('Receiving bank account is required for bank transfer.');
        }

        if ($input->salesPointId !== null && $input->salesPointId <= 0) {
            throw new \InvalidArgumentException('Sales point ID must be greater than zero.');
        }

        if (trim($input->idempotencyKey) === '') {
            throw new \InvalidArgumentException(
                'Idempotency key cannot be empty.',
            );
        }
    }

    private function resolveBankAccount(CheckoutInput $input): ?PaymentBankAccount
    {
        if ($input->payment->method !== PaymentMethod::BANK_TRANSFER) {
            return null;
        }

        if ($input->bankAccountId === null) {
            throw new \InvalidArgumentException('Receiving bank account is required for bank transfer.');
        }

        $account = $this->paymentBankAccountRepository->findById($input->bankAccountId);
        if (!$account instanceof PaymentBankAccount || !$account->isActive()) {
            throw new \DomainException('Receiving bank account is not found or inactive.');
        }

        return $account;
    }

    private function buildRequestFingerprint(
        CheckoutInput $input,
    ): string {
        $items = [];

        foreach ($input->items as $item) {
            $items[] = [
                'productId' => $item->productId,
                'quantity' => $item->quantity,
            ];
        }

        usort(
            $items,
            static fn (
                array $left,
                array $right,
            ): int => $left['productId'] <=> $right['productId'],
        );

        $payload = [
            'items' => $items,
            'customerId' => $input->customerId,
            'salesPointId' => $input->salesPointId,
            'payment' => [
                'method' => $input->payment->method->value,
                'amount' => $input->payment->amount->toDecimal(),
                'tenderedAmount' => $input->payment->tenderedAmount?->toDecimal(),
                'bankAccountId' => $input->bankAccountId,
            ],
            'note' => $input->note,
        ];

        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    private function replay(
        IdempotencyDecision $decision,
    ): CheckoutResult {
        $record = $decision->record;

        if ($record === null) {
            throw new \LogicException(
                'Replay decision must contain an idempotency record.',
            );
        }

        if ($record->getResponseStatus() !== self::RESPONSE_STATUS_OK) {
            throw new \DomainException(
                'Stored checkout response cannot be replayed.',
            );
        }

        $body = $record->getResponseBody();

        if ($body === null) {
            throw new \LogicException(
                'Completed checkout has no stored response body.',
            );
        }

        return new CheckoutResult(
            orderId: isset($body['orderId']) ? (int) $body['orderId'] : null,
            orderNumber: isset($body['orderNumber']) ? (string) $body['orderNumber'] : null,
            total: Money::fromDecimal((string) $body['total']),
            paidAmount: Money::fromDecimal((string) $body['paidAmount']),
            debtAmount: Money::fromDecimal((string) $body['debtAmount']),
            tenderedAmount: Money::fromDecimal((string) ($body['tenderedAmount'] ?? $body['paidAmount'])),
            changeAmount: Money::fromDecimal((string) ($body['changeAmount'] ?? '0.00')),
            status: isset($body['status']) && $body['status'] !== null ? OrderStatus::from((string) $body['status']) : null,
            paymentReference: $body['paymentReference'] ?? null,
            paymentReferenceExpiresAt: $body['paymentReferenceExpiresAt'] ?? null,
            paymentReferenceTransferContent: $body['paymentReferenceTransferContent'] ?? null,
            paymentReferenceQrUrl: $body['paymentReferenceQrUrl'] ?? null,
            bankTransferCompletionPolicy: $body['bankTransferCompletionPolicy'] ?? null,
            paymentSessionId: isset($body['paymentSessionId']) ? (int) $body['paymentSessionId'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(
        CheckoutResult $result,
    ): array {
        return [
            'orderId' => $result->orderId,
            'orderNumber' => $result->orderNumber,
            'total' => $result->total->toDecimal(),
            'paidAmount' => $result->paidAmount->toDecimal(),
            'debtAmount' => $result->debtAmount->toDecimal(),
            'tenderedAmount' => $result->tenderedAmount->toDecimal(),
            'changeAmount' => $result->changeAmount->toDecimal(),
            'status' => $result->status?->value,
            'paymentReference' => $result->paymentReference,
            'paymentReferenceExpiresAt' => $result->paymentReferenceExpiresAt,
            'paymentReferenceTransferContent' => $result->paymentReferenceTransferContent,
            'paymentReferenceQrUrl' => $result->paymentReferenceQrUrl,
            'bankTransferCompletionPolicy' => $result->bankTransferCompletionPolicy,
            'paymentSessionId' => $result->paymentSessionId,
        ];
    }

    private function calculateChange(
        CheckoutInput $input,
        Money $total,
        Money $tenderedAmount,
    ): Money {
        if (
            $input->payment->method !== PaymentMethod::CASH
            || !$tenderedAmount->isGreaterThan($total)
        ) {
            return Money::zero();
        }

        return $tenderedAmount->subtract($total);
    }

    private function resolveFailureStatus(
        \Throwable $exception,
    ): int {
        return $exception instanceof \DomainException
            ? 422
            : 500;
    }
}
