<?php

declare(strict_types=1);

namespace App\Application\Order\Command\CancelOrder;

use App\Application\Common\Idempotency\IdempotencyDecision;
use App\Application\Common\Idempotency\IdempotencyDecisionType;
use App\Application\Common\Idempotency\IdempotencyPort;
use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Order\Command\Checkout\ProductLockingInterface;
use App\Application\Security\ActorContext;
use App\Application\Security\ActorContextProviderInterface;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Debt\Repository\DebtRepositoryInterface;
use App\Domain\Order\Enum\FinancialReversalType;
use App\Domain\Order\OrderFinancialReversal;
use App\Domain\Order\Order;
use App\Domain\Order\Repository\OrderFinancialReversalRepositoryInterface;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\Stock\StockMovement;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserSession;

final readonly class CancelOrderHandler
{
    private const OPERATION = 'order_cancel';
    private const RESPONSE_STATUS_OK = 200;

    public function __construct(
        private ActorContextProviderInterface $actorContextProvider,
        private TransactionManagerInterface $transactionManager,
        private IdempotencyPort $idempotency,
        private OrderRepositoryInterface $orderRepository,
        private OrderFinancialReversalRepositoryInterface $reversalRepository,
        private ProductLockingInterface $productLocking,
        private StockMovementRepositoryInterface $stockMovementRepository,
        private DebtRepositoryInterface $debtRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
        private UserRepositoryInterface $userRepository,
        private UserSessionRepositoryInterface $userSessionRepository,
    ) {
    }

    public function __invoke(CancelOrderInput $input): CancelOrderResult
    {
        $this->validateInput($input);
        $actor = $this->actorContextProvider->get();
        $fingerprint = hash('sha256', json_encode([
            'orderId' => $input->orderId,
            'reason' => trim($input->reason),
        ], JSON_THROW_ON_ERROR));

        $decision = $this->transactionManager->run(fn (): IdempotencyDecision => $this->idempotency->start(
            $actor->userId,
            self::OPERATION,
            $input->idempotencyKey,
            $fingerprint,
        ));

        return match ($decision->type) {
            IdempotencyDecisionType::REPLAY => $this->replay($decision),
            IdempotencyDecisionType::IN_PROGRESS => throw new \DomainException(
                'Order cancellation with this idempotency key is already in progress.',
            ),
            IdempotencyDecisionType::EXECUTE => $this->executeWithIdempotency($input, $decision, $actor),
        };
    }

    private function executeWithIdempotency(
        CancelOrderInput $input,
        IdempotencyDecision $decision,
        ActorContext $actor,
    ): CancelOrderResult {
        try {
            $result = $this->transactionManager->run(
                function (TransactionContextInterface $transaction) use ($input, $actor): CancelOrderResult {
                    $user = $this->resolveUser($actor);
                    $session = $this->resolveSession($actor);
                    $order = $this->orderRepository->findByIdForUpdate($input->orderId);

                    if ($order === null) {
                        throw new \RuntimeException('Order not found.');
                    }

                    if (!$order->isCompleted()) {
                        if ($order->isCancelled()) {
                            throw new \DomainException('Order has already been cancelled.');
                        }
                        if ($order->isRefunded()) {
                            throw new \DomainException('Order has already been refunded.');
                        }
                        throw new \DomainException('Only completed orders can be cancelled.');
                    }

                    $existingReversal = $this->reversalRepository->findByOrderId($input->orderId);
                    if ($existingReversal !== null) {
                        throw new \DomainException('Order financial reversal has already been processed.');
                    }

                    $products = $this->lockProducts($order);
                    $paidAmountBefore = $order->getPaidAmount();
                    $debtAmountBefore = $order->getDebtAmount();
                    $reversedAmount = $paidAmountBefore;
                    $restoredQuantity = 0;

                    if ($reversedAmount->isPositive()) {
                        $this->reversalRepository->save(new OrderFinancialReversal(
                            order: $order,
                            user: $user,
                            type: FinancialReversalType::CANCEL,
                            amount: $reversedAmount,
                            reason: $input->reason,
                        ));
                    }

                    foreach ($products as $productId => $product) {
                        $quantity = $this->quantityForProduct($order, $productId);
                        $before = $product->getStockQuantity();
                        $product->increaseStock($quantity);
                        $restoredQuantity += $quantity;

                        $this->stockMovementRepository->save(new StockMovement(
                            product: $product,
                            type: StockMovementType::SALE_REVERSAL,
                            quantityBefore: $before,
                            quantityChange: $quantity,
                            user: $user,
                            order: $order,
                            session: $session,
                            reason: 'Order cancellation: '.$input->reason,
                        ));
                    }

                    $debt = $this->debtRepository->findByOrderIdForUpdate($input->orderId);
                    if ($debt !== null && !$debt->getStatus()->isReversed()) {
                        $debt->reverse();
                    }

                    $oldStatus = $order->getStatus()->value;
                    $order->cancel();

                    $this->auditLogRepository->save(new AuditLog(
                        action: 'ORDER_CANCEL',
                        user: $user,
                        session: $session,
                        entityType: 'Order',
                        entityId: (string) $order->getId(),
                        oldValues: [
                            'status' => $oldStatus,
                            'paidAmount' => $paidAmountBefore->toDecimal(),
                            'debtAmount' => $debtAmountBefore->toDecimal(),
                        ],
                        newValues: [
                            'status' => $order->getStatus()->value,
                            'reversedAmount' => $reversedAmount->toDecimal(),
                            'restoredQuantity' => $restoredQuantity,
                            'reason' => $input->reason,
                            'requestId' => $actor->requestId,
                        ],
                    ));

                    $transaction->flush();

                    return new CancelOrderResult(
                        orderId: $order->getId() ?? throw new \LogicException('Order ID was not generated.'),
                        orderNumber: $order->getOrderNumber()->value(),
                        status: $order->getStatus(),
                        reversedAmount: $reversedAmount,
                        restoredQuantity: $restoredQuantity,
                    );
                },
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->run(function () use ($decision, $exception): void {
                $this->idempotency->fail(
                    $decision,
                    $this->failureStatus($exception),
                    ['error' => $exception->getMessage()],
                );
            });
            throw $exception;
        }

        $this->transactionManager->run(function () use ($decision, $result): void {
            $this->idempotency->complete(
                $decision,
                self::RESPONSE_STATUS_OK,
                $this->serializeResult($result),
            );
        });

        return $result;
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

    private function resolveUser(ActorContext $context): User
    {
        $user = $this->userRepository->findById($context->userId);
        if ($user === null || !$user->isActive()) {
            throw new \DomainException('Authenticated user is inactive or not found.');
        }
        return $user;
    }

    private function resolveSession(ActorContext $context): ?UserSession
    {
        return $context->sessionId === null ? null : $this->userSessionRepository->findBySessionIdentifier($context->sessionId);
    }

    private function validateInput(CancelOrderInput $input): void
    {
        if ($input->orderId <= 0) {
            throw new \InvalidArgumentException('Order ID must be greater than zero.');
        }
        if (trim($input->reason) === '') {
            throw new \InvalidArgumentException('Cancellation reason is required.');
        }
        if (mb_strlen(trim($input->reason)) > 255) {
            throw new \InvalidArgumentException('Cancellation reason cannot exceed 255 characters.');
        }
        if (trim($input->idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key cannot be empty.');
        }
    }

    /** @return array<string,mixed> */
    private function serializeResult(CancelOrderResult $result): array
    {
        return [
            'orderId' => $result->orderId,
            'orderNumber' => $result->orderNumber,
            'status' => $result->status->value,
            'reversedAmount' => $result->reversedAmount->toDecimal(),
            'restoredQuantity' => $result->restoredQuantity,
        ];
    }

    private function replay(IdempotencyDecision $decision): CancelOrderResult
    {
        $body = $decision->record?->getResponseBody();
        if (!is_array($body)) {
            throw new \LogicException('Completed order cancellation has no response body.');
        }
        return new CancelOrderResult(
            (int) $body['orderId'],
            (string) $body['orderNumber'],
            \App\Domain\Order\Enum\OrderStatus::from((string) $body['status']),
            Money::fromDecimal((string) $body['reversedAmount']),
            (int) $body['restoredQuantity'],
        );
    }

    private function failureStatus(\Throwable $exception): int
    {
        if ($exception instanceof \InvalidArgumentException) return 400;
        if ($exception instanceof \RuntimeException && $exception->getMessage() === 'Order not found.') return 404;
        if ($exception instanceof \DomainException) return 409;
        return 500;
    }
}
