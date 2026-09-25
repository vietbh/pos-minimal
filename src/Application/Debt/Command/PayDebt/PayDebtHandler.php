<?php

declare(strict_types=1);

namespace App\Application\Debt\Command\PayDebt;

use App\Application\Common\Idempotency\IdempotencyDecision;
use App\Application\Common\Idempotency\IdempotencyDecisionType;
use App\Application\Common\Idempotency\IdempotencyPort;
use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Security\ActorContext;
use App\Application\Security\ActorContextProviderInterface;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Debt\Debt;
use App\Domain\Debt\DebtPayment;
use App\Domain\Debt\Repository\DebtPaymentRepositoryInterface;
use App\Domain\Debt\Repository\DebtRepositoryInterface;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Repository\UserSessionRepositoryInterface;
use App\Domain\User\User;

final readonly class PayDebtHandler
{
    private const OPERATION = 'debt_payment';

    public function __construct(
        private ActorContextProviderInterface $actorContextProvider,
        private TransactionManagerInterface $transactionManager,
        private IdempotencyPort $idempotency,
        private DebtRepositoryInterface $debtRepository,
        private DebtPaymentRepositoryInterface $paymentRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
        private UserRepositoryInterface $userRepository,
        private UserSessionRepositoryInterface $userSessionRepository,
    ) {
    }

    public function __invoke(PayDebtInput $input): PayDebtResult
    {
        $this->validate($input);

        $actor = $this->actorContextProvider->get();
        $fingerprint = $this->fingerprint($input);

        $decision = $this->transactionManager->run(
            fn (): IdempotencyDecision => $this->idempotency->start(
                $actor->userId,
                self::OPERATION,
                $input->idempotencyKey,
                $fingerprint,
            ),
        );

        return match ($decision->type) {
            IdempotencyDecisionType::REPLAY => $this->replay($decision),
            IdempotencyDecisionType::IN_PROGRESS => throw new \DomainException(
                'Debt payment with this idempotency key is already in progress.',
            ),
            IdempotencyDecisionType::EXECUTE => $this->executeWithIdempotency(
                $input,
                $decision,
                $actor,
            ),
        };
    }

    private function executeWithIdempotency(
        PayDebtInput $input,
        IdempotencyDecision $decision,
        ActorContext $actor,
    ): PayDebtResult {
        try {
            $result = $this->transactionManager->run(
                function (TransactionContextInterface $tx) use ($input, $actor): PayDebtResult {
                    $user = $this->userRepository->findById($actor->userId);

                    if (!$user instanceof User || !$user->isActive()) {
                        throw new \DomainException(
                            'Authenticated user is inactive or not found.',
                        );
                    }

                    /*
                     * This lock is the financial concurrency boundary.
                     * All reads that determine the payable remainder happen
                     * after the lock is acquired and inside the same DB
                     * transaction as the payment insert.
                     */
                    $debt = $this->debtRepository->findByIdForUpdate($input->debtId);

                    if (!$debt instanceof Debt) {
                        throw new \RuntimeException('Debt not found.');
                    }

                    if (!$debt->canReceivePayment()) {
                        throw new \DomainException('Debt cannot receive payment.');
                    }

                    $remainingBefore = $debt->getRemainingAmount();
                    $amount = Money::fromDecimal($input->amount);

                    if ($amount->isGreaterThan($remainingBefore)) {
                        throw new \DomainException(
                            'Debt payment cannot exceed remaining debt.',
                        );
                    }

                    $payment = new DebtPayment($amount, $user);
                    $payment->assignDebt($debt);
                    $debt->addPayment($payment);
                    // Persist both sides explicitly. The debt status/remaining
                    // balance is part of the same financial mutation as the payment.
                    $this->debtRepository->save($debt);
                    $this->paymentRepository->save($payment);

                    $session = $actor->sessionId === null
                        ? null
                        : $this->userSessionRepository->findBySessionIdentifier(
                            $actor->sessionId,
                        );

                    $this->auditLogRepository->save(
                        new AuditLog(
                            'DEBT_PAYMENT',
                            $user,
                            $session,
                            'Debt',
                            (string) $debt->getId(),
                            [
                                'remainingAmount' => $remainingBefore->toDecimal(),
                            ],
                            [
                                'amount' => $amount->toDecimal(),
                                'paidAmount' => $debt->getPaidAmount()->toDecimal(),
                                'remainingAmount' => $debt->getRemainingAmount()->toDecimal(),
                                'status' => $debt->getStatus()->value,
                                'requestId' => $actor->requestId,
                            ],
                        ),
                    );

                    $tx->flush();

                    if ($payment->getId() === null) {
                        throw new \LogicException(
                            'Debt payment ID was not generated.',
                        );
                    }

                    return new PayDebtResult(
                        $debt->getId() ?? 0,
                        $payment->getId(),
                        $amount->toDecimal(),
                        $debt->getPaidAmount()->toDecimal(),
                        $debt->getRemainingAmount()->toDecimal(),
                        $debt->getStatus()->value,
                    );
                },
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->run(
                function () use ($decision, $exception): void {
                    $this->idempotency->fail(
                        $decision,
                        $this->failureStatus($exception),
                        ['error' => $exception->getMessage()],
                    );
                },
            );

            throw $exception;
        }

        $this->transactionManager->run(
            function () use ($decision, $result): void {
                $this->idempotency->complete(
                    $decision,
                    200,
                    $this->serialize($result),
                );
            },
        );

        return $result;
    }

    private function validate(PayDebtInput $input): void
    {
        if ($input->debtId <= 0) {
            throw new \InvalidArgumentException(
                'Debt ID must be greater than zero.',
            );
        }

        if (trim($input->amount) === '') {
            throw new \InvalidArgumentException(
                'Payment amount is required.',
            );
        }

        $amount = Money::fromDecimal($input->amount);

        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException(
                'Debt payment amount must be greater than zero.',
            );
        }

        if (trim($input->idempotencyKey) === '') {
            throw new \InvalidArgumentException(
                'Idempotency key cannot be empty.',
            );
        }
    }

    private function fingerprint(PayDebtInput $input): string
    {
        return hash(
            'sha256',
            json_encode(
                [
                    'debtId' => $input->debtId,
                    'amount' => Money::fromDecimal($input->amount)->toDecimal(),
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    /** @return array<string,string|int> */
    private function serialize(PayDebtResult $result): array
    {
        return [
            'debtId' => $result->debtId,
            'paymentId' => $result->paymentId,
            'amount' => $result->amount,
            'paidAmount' => $result->paidAmount,
            'remainingAmount' => $result->remainingAmount,
            'status' => $result->status,
        ];
    }

    private function replay(IdempotencyDecision $decision): PayDebtResult
    {
        $body = $decision->record?->getResponseBody();

        if (!is_array($body)) {
            throw new \LogicException(
                'Completed debt payment has no response body.',
            );
        }

        return new PayDebtResult(
            (int) $body['debtId'],
            (int) $body['paymentId'],
            (string) $body['amount'],
            (string) $body['paidAmount'],
            (string) $body['remainingAmount'],
            (string) $body['status'],
        );
    }

    private function failureStatus(\Throwable $exception): int
    {
        if ($exception instanceof \InvalidArgumentException) {
            return 400;
        }

        if (
            $exception instanceof \RuntimeException
            && $exception->getMessage() === 'Debt not found.'
        ) {
            return 404;
        }

        if ($exception instanceof \DomainException) {
            return 409;
        }

        return 500;
    }
}
