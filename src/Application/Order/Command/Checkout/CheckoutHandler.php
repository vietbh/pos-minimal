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
        private DebtRepositoryInterface $debtRepository,
        private StockMovementRepositoryInterface $stockMovementRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
    ) {
    }

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
            /*
             * Transaction #2 has already rolled back.
             *
             * Transaction #3
             *
             * Persist PROCESSING -> FAILED independently from
             * the failed business transaction.
             */
            $this->transactionManager->run(
                function () use (
                    $decision,
                    $exception,
                ): void {
                    $this->idempotency->fail(
                        decision: $decision,
                        responseStatus: $this->resolveFailureStatus(
                            $exception,
                        ),
                        responseBody: [
                            'error' => $exception->getMessage(),
                        ],
                    );
                },
            );

            throw $exception;
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

    private function execute(
        CheckoutInput $input,
        IdempotencyDecision $decision,
        TransactionContextInterface $transaction,
        ActorContext $actorContext,
    ): CheckoutResult {
        $user = $this->resolveUser($actorContext);
        $session = $this->resolveSession($actorContext);
        $customer = $this->resolveCustomer($input->customerId);

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

        $order = new Order(
            orderNumber: $this->generateOrderNumber(),
            user: $user,
            customer: $customer,
            note: $input->note,
        );

        foreach ($productIds as $productId) {
            $product = $products[$productId];
            $quantity = $quantities[$productId];

            $this->assertProductCanBeSold(
                $product,
                $quantity,
            );

            $order->addItem(
                new OrderItem(
                    product: $product,
                    quantity: $quantity,
                    unitPrice: $product->getSellingPrice(),
                ),
            );
        }

        /*
         * The Order calculates its total from authoritative
         * OrderItem values. Client supplied totals are never trusted.
         */
        $order->recalculateTotals();

        $paymentAmount = $input->payment->amount;

        if ($paymentAmount->isPositive()) {
            $payment = new Payment(
                amount: $paymentAmount,
                method: $input->payment->method,
                user: $user,
            );

            $order->addPayment($payment);
            $this->paymentRepository->save($payment);
        }

        /*
         * Domain validates payment <= total and performs the
         * final Order state transition.
         */
        $order->complete();

        $this->orderRepository->save($order);

        /*
         * The Order ID is generated by Doctrine.
         *
         * Flush while the transaction is still open so the
         * application can safely construct the result and audit
         * record using the database identity.
         */
        $transaction->flush();

        $orderId = $order->getId();

        if ($orderId === null) {
            throw new \LogicException(
                'Order ID was not generated after transaction flush.',
            );
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
                    'orderNumber' => $order
                        ->getOrderNumber()
                        ->value(),
                    'status' => $order
                        ->getStatus()
                        ->value,
                    'total' => $order
                        ->getTotal()
                        ->toDecimal(),
                    'paidAmount' => $order
                        ->getPaidAmount()
                        ->toDecimal(),
                    'debtAmount' => $order
                        ->getDebtAmount()
                        ->toDecimal(),
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
            status: $order->getStatus(),
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

        if (trim($input->idempotencyKey) === '') {
            throw new \InvalidArgumentException(
                'Idempotency key cannot be empty.',
            );
        }
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
            'payment' => [
                'method' => $input->payment->method->value,
                'amount' => $input->payment->amount->toDecimal(),
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
            orderId: (int) $body['orderId'],
            orderNumber: (string) $body['orderNumber'],
            total: Money::fromDecimal((string) $body['total']),
            paidAmount: Money::fromDecimal((string) $body['paidAmount']),
            debtAmount: Money::fromDecimal((string) $body['debtAmount']),
            status: OrderStatus::from((string) $body['status']),
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
            'status' => $result->status->value,
        ];
    }

    private function resolveFailureStatus(
        \Throwable $exception,
    ): int {
        return $exception instanceof \DomainException
            ? 422
            : 500;
    }
}
