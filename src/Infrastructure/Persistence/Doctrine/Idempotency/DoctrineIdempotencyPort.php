<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Idempotency;

use App\Application\Common\Idempotency\IdempotencyConflict;
use App\Application\Common\Idempotency\IdempotencyDecision;
use App\Application\Common\Idempotency\IdempotencyPort;
use App\Domain\Idempotency\IdempotencyRecord;
use App\Domain\Idempotency\Repository\IdempotencyRecordRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineIdempotencyPort implements IdempotencyPort
{
    public function __construct(
        private IdempotencyRecordRepositoryInterface $recordRepository,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function start(
        int $userId,
        string $operation,
        string $idempotencyKey,
        string $requestFingerprint,
    ): IdempotencyDecision {
        $existing = $this->recordRepository->findByKey(
            $userId,
            $idempotencyKey,
        );

        if ($existing !== null) {
            if ($existing->getOperation() !== $operation) {
                throw new IdempotencyConflict();
            }

            if ($existing->getRequestHash() !== $requestFingerprint) {
                throw new IdempotencyConflict();
            }

            if ($existing->isCompleted()) {
                return IdempotencyDecision::replay($existing);
            }

            if ($existing->isProcessing()) {
                return IdempotencyDecision::inProgress($existing);
            }

            if ($existing->isFailed()) {
                throw new \DomainException(
                    'This idempotency key belongs to a failed operation.',
                );
            }

            throw new \LogicException(
                'Unknown idempotency record state.',
            );
        }

        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new \RuntimeException(
                sprintf('User %d was not found.', $userId),
            );
        }

        $record = new IdempotencyRecord(
            $user,
            $idempotencyKey,
            $operation,
            $requestFingerprint,
        );

        $this->recordRepository->save($record);

        return IdempotencyDecision::execute($record);
    }

    public function complete(
        IdempotencyDecision $decision,
        int $responseStatus,
        array $responseBody,
    ): void {
        $record = $decision->record;

        if ($record === null) {
            throw new \LogicException(
                'An idempotency decision must contain a record.',
            );
        }

        $record->markCompleted(
            $responseStatus,
            $responseBody,
        );

        $this->recordRepository->save($record);
    }

    public function fail(
        IdempotencyDecision $decision,
        int $responseStatus,
        ?array $responseBody = null,
    ): void {
        $record = $decision->record;

        if ($record === null) {
            throw new \LogicException(
                'An idempotency decision must contain a record.',
            );
        }

        $record->markFailed(
            $responseStatus,
            $responseBody,
        );

        $this->recordRepository->save($record);
    }

}
