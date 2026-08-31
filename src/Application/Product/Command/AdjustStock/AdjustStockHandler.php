<?php

declare(strict_types=1);

namespace App\Application\Product\Command\AdjustStock;

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
use App\Domain\Product\Product;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\Stock\StockMovement;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserSession;

final readonly class AdjustStockHandler
{
    private const OPERATION = 'stock_adjustment';
    private const RESPONSE_STATUS_OK = 200;

    public function __construct(
        private ActorContextProviderInterface $actorContextProvider,
        private TransactionManagerInterface $transactionManager,
        private IdempotencyPort $idempotency,
        private ProductLockingInterface $productLocking,
        private UserRepositoryInterface $userRepository,
        private UserSessionRepositoryInterface $userSessionRepository,
        private StockMovementRepositoryInterface $stockMovementRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
    ) {
    }

    public function __invoke(AdjustStockInput $input): AdjustStockResult
    {
        $this->validateInput($input);
        $actorContext = $this->actorContextProvider->get();
        $fingerprint = $this->fingerprint($input);

        $decision = $this->transactionManager->run(
            fn (): IdempotencyDecision => $this->idempotency->start(
                userId: $actorContext->userId,
                operation: self::OPERATION,
                idempotencyKey: $input->idempotencyKey,
                requestFingerprint: $fingerprint,
            ),
        );

        return match ($decision->type) {
            IdempotencyDecisionType::REPLAY => $this->replay($decision),
            IdempotencyDecisionType::IN_PROGRESS => throw new \DomainException(
                'Stock adjustment with this idempotency key is already in progress.',
            ),
            IdempotencyDecisionType::EXECUTE => $this->executeWithIdempotencyOutcome(
                $input,
                $decision,
                $actorContext,
            ),
        };
    }

    private function executeWithIdempotencyOutcome(
        AdjustStockInput $input,
        IdempotencyDecision $decision,
        ActorContext $actorContext,
    ): AdjustStockResult {
        try {
            $result = $this->transactionManager->run(
                function (
                    TransactionContextInterface $transaction,
                ) use ($input, $actorContext): AdjustStockResult {
                    $user = $this->resolveUser($actorContext);
                    $session = $this->resolveSession($actorContext);

                    $product = $this->productLocking->lock($input->productId);
                    $quantityBefore = $product->getStockQuantity();
                    $quantityAfter = $quantityBefore + $input->quantityChange;

                    if ($quantityAfter < 0) {
                        throw new \DomainException(
                            sprintf(
                                'Insufficient stock for product %d.',
                                $input->productId,
                            ),
                        );
                    }

                    if ($input->quantityChange > 0) {
                        $product->increaseStock($input->quantityChange);
                    } else {
                        $product->decreaseStock(-$input->quantityChange);
                    }

                    $movement = new StockMovement(
                        product: $product,
                        type: StockMovementType::ADJUSTMENT,
                        quantityBefore: $quantityBefore,
                        quantityChange: $input->quantityChange,
                        user: $user,
                        session: $session,
                        reason: $input->reason,
                    );

                    $this->stockMovementRepository->save($movement);
                    $this->auditLogRepository->save(new AuditLog(
                        action: 'STOCK_ADJUSTED',
                        user: $user,
                        session: $session,
                        entityType: 'Product',
                        entityId: (string) $input->productId,
                        oldValues: [
                            'stockQuantity' => $quantityBefore,
                        ],
                        newValues: [
                            'stockQuantity' => $quantityAfter,
                            'quantityChange' => $input->quantityChange,
                            'reason' => $input->reason,
                        ],
                    ));

                    $transaction->flush();

                    $movementId = $movement->getId();
                    if ($movementId === null) {
                        throw new \LogicException(
                            'Stock movement ID was not generated after flush.',
                        );
                    }

                    return new AdjustStockResult(
                        productId: $input->productId,
                        quantityBefore: $quantityBefore,
                        quantityChange: $input->quantityChange,
                        quantityAfter: $quantityAfter,
                        stockMovementId: $movementId,
                    );
                },
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->run(
                function () use ($decision, $exception): void {
                    $this->idempotency->fail(
                        decision: $decision,
                        responseStatus: $this->resolveFailureStatus($exception),
                        responseBody: ['error' => $exception->getMessage()],
                    );
                },
            );

            throw $exception;
        }

        $this->transactionManager->run(
            function () use ($decision, $result): void {
                $this->idempotency->complete(
                    decision: $decision,
                    responseStatus: self::RESPONSE_STATUS_OK,
                    responseBody: $this->serializeResult($result),
                );
            },
        );

        return $result;
    }

    private function replay(IdempotencyDecision $decision): AdjustStockResult
    {
        $body = $decision->record?->getResponseBody();

        if (!is_array($body)) {
            throw new \LogicException(
                'Completed stock adjustment has no response body.',
            );
        }

        return new AdjustStockResult(
            productId: (int) $body['productId'],
            quantityBefore: (int) $body['quantityBefore'],
            quantityChange: (int) $body['quantityChange'],
            quantityAfter: (int) $body['quantityAfter'],
            stockMovementId: (int) $body['stockMovementId'],
        );
    }

    /** @return array<string,mixed> */
    private function serializeResult(AdjustStockResult $result): array
    {
        return [
            'productId' => $result->productId,
            'quantityBefore' => $result->quantityBefore,
            'quantityChange' => $result->quantityChange,
            'quantityAfter' => $result->quantityAfter,
            'stockMovementId' => $result->stockMovementId,
        ];
    }

    private function resolveUser(ActorContext $context): User
    {
        $user = $this->userRepository->findById($context->userId);

        if ($user === null) {
            throw new \DomainException('Authenticated user was not found.');
        }

        if (!$user->isActive()) {
            throw new \DomainException('Authenticated user is inactive.');
        }

        return $user;
    }

    private function resolveSession(ActorContext $context): ?UserSession
    {
        if ($context->sessionId === null) {
            return null;
        }

        return $this->userSessionRepository->findBySessionIdentifier(
            $context->sessionId,
        );
    }

    private function validateInput(AdjustStockInput $input): void
    {
        if ($input->productId <= 0) {
            throw new \InvalidArgumentException(
                'Product ID must be greater than zero.',
            );
        }

        if ($input->quantityChange === 0) {
            throw new \InvalidArgumentException(
                'Stock adjustment cannot be zero.',
            );
        }

        if (trim($input->idempotencyKey) === '') {
            throw new \InvalidArgumentException(
                'Idempotency key cannot be empty.',
            );
        }

        if ($input->reason !== null && mb_strlen(trim($input->reason)) > 255) {
            throw new \InvalidArgumentException(
                'Stock adjustment reason cannot exceed 255 characters.',
            );
        }
    }

    private function fingerprint(AdjustStockInput $input): string
    {
        return hash(
            'sha256',
            json_encode([
                'productId' => $input->productId,
                'quantityChange' => $input->quantityChange,
                'reason' => $input->reason !== null ? trim($input->reason) : null,
            ], JSON_THROW_ON_ERROR),
        );
    }

    private function resolveFailureStatus(\Throwable $exception): int
    {
        if ($exception instanceof \InvalidArgumentException) {
            return 400;
        }

        if ($exception instanceof \DomainException) {
            return 409;
        }

        return 500;
    }
}
