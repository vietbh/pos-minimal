<?php

declare(strict_types=1);

namespace App\Application\Order\Command\RefundOrder;

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
use App\Domain\Order\Order;
use App\Domain\Order\OrderFinancialReversal;
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

final readonly class RefundOrderHandler
{
    private const OPERATION = 'order_refund';
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

    public function __invoke(RefundOrderInput $input): RefundOrderResult
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
                'Order refund with this idempotency key is already in progress.',
            ),
            IdempotencyDecisionType::EXECUTE => $this->executeWithIdempotency($input, $decision, $actor),
        };
    }

    private function executeWithIdempotency(
        RefundOrderInput $input,
        IdempotencyDecision $decision,
        ActorContext $actor,
    ): RefundOrderResult {
        try {
            $result = $this->transactionManager->run(
                function (TransactionContextInterface $transaction) use ($input, $actor): RefundOrderResult {
                    $user = $this->resolveUser($actor);
                    $session = $this->resolveSession($actor);
                    $order = $this->orderRepository->findByIdForUpdate($input->orderId);
                    if ($order === null) {
                        throw new \RuntimeException('Order not found.');
                    }
                    if (!$order->isCompleted()) {
                        if ($order->isRefunded()) {
                            throw new \DomainException('Order has already been refunded.');
                        }
                        if ($order->isCancelled()) {
                            throw new \DomainException('Order has already been cancelled.');
                        }
                        throw new \DomainException('Only completed orders can be refunded.');
                    }

                    if ($this->reversalRepository->findByOrderId($input->orderId) !== null) {
                        throw new \DomainException('Order financial reversal has already been processed.');
                    }

                    $products = $this->lockProducts($order);
                    $paidAmountBefore = $order->getPaidAmount();
                    $debtAmountBefore = $order->getDebtAmount();
                    $refundedAmount = $paidAmountBefore;
                    $restoredQuantity = 0;

                    if ($refundedAmount->isPositive()) {
                        $this->reversalRepository->save(new OrderFinancialReversal(
                            order: $order,
                            user: $user,
                            type: FinancialReversalType::REFUND,
                            amount: $refundedAmount,
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
                            reason: 'Order refund: '.$input->reason,
                        ));
                    }

                    $debt = $this->debtRepository->findByOrderIdForUpdate($input->orderId);
                    if ($debt !== null && !$debt->getStatus()->isReversed()) {
                        $debt->reverse();
                    }

                    $oldStatus = $order->getStatus()->value;
                    $order->refund();

                    $this->auditLogRepository->save(new AuditLog(
                        action: 'ORDER_REFUND',
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
                            'refundedAmount' => $refundedAmount->toDecimal(),
                            'restoredQuantity' => $restoredQuantity,
                            'reason' => $input->reason,
                            'requestId' => $actor->requestId,
                        ],
                    ));

                    $transaction->flush();
                    return new RefundOrderResult(
                        orderId: $order->getId() ?? throw new \LogicException('Order ID was not generated.'),
                        orderNumber: $order->getOrderNumber()->value(),
                        status: $order->getStatus(),
                        refundedAmount: $refundedAmount,
                        restoredQuantity: $restoredQuantity,
                    );
                },
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->run(function () use ($decision, $exception): void {
                $this->idempotency->fail($decision, $this->failureStatus($exception), ['error' => $exception->getMessage()]);
            });
            throw $exception;
        }

        $this->transactionManager->run(function () use ($decision, $result): void {
            $this->idempotency->complete($decision, self::RESPONSE_STATUS_OK, $this->serializeResult($result));
        });
        return $result;
    }

    /** @return array<int, \App\Domain\Product\Product> */
    private function lockProducts(Order $order): array
    {
        $ids = [];
        foreach ($order->getItems() as $item) {
            $id = $item->getProduct()->getId();
            if ($id === null) throw new \LogicException('Order item product has no ID.');
            $ids[$id] = true;
        }
        $ids = array_keys($ids);
        sort($ids, SORT_NUMERIC);
        $products = [];
        foreach ($ids as $id) $products[$id] = $this->productLocking->lock($id);
        return $products;
    }

    private function quantityForProduct(Order $order, int $productId): int
    {
        $quantity = 0;
        foreach ($order->getItems() as $item) {
            if ($item->getProduct()->getId() === $productId) $quantity += $item->getQuantity();
        }
        return $quantity;
    }

    private function resolveUser(ActorContext $context): User
    {
        $user = $this->userRepository->findById($context->userId);
        if ($user === null || !$user->isActive()) throw new \DomainException('Authenticated user is inactive or not found.');
        return $user;
    }

    private function resolveSession(ActorContext $context): ?UserSession
    {
        return $context->sessionId === null ? null : $this->userSessionRepository->findBySessionIdentifier($context->sessionId);
    }

    private function validateInput(RefundOrderInput $input): void
    {
        if ($input->orderId <= 0) throw new \InvalidArgumentException('Order ID must be greater than zero.');
        if (trim($input->reason) === '') throw new \InvalidArgumentException('Refund reason is required.');
        if (mb_strlen(trim($input->reason)) > 255) throw new \InvalidArgumentException('Refund reason cannot exceed 255 characters.');
        if (trim($input->idempotencyKey) === '') throw new \InvalidArgumentException('Idempotency key cannot be empty.');
    }

    /** @return array<string,mixed> */
    private function serializeResult(RefundOrderResult $result): array
    {
        return [
            'orderId' => $result->orderId,
            'orderNumber' => $result->orderNumber,
            'status' => $result->status->value,
            'refundedAmount' => $result->refundedAmount->toDecimal(),
            'restoredQuantity' => $result->restoredQuantity,
        ];
    }

    private function replay(IdempotencyDecision $decision): RefundOrderResult
    {
        $body = $decision->record?->getResponseBody();
        if (!is_array($body)) throw new \LogicException('Completed order refund has no response body.');
        return new RefundOrderResult(
            (int) $body['orderId'],
            (string) $body['orderNumber'],
            \App\Domain\Order\Enum\OrderStatus::from((string) $body['status']),
            Money::fromDecimal((string) $body['refundedAmount']),
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
