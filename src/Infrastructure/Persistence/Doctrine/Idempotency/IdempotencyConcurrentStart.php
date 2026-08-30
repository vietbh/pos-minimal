<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Idempotency;

use App\Application\Common\Idempotency\IdempotencyConflict;
use App\Application\Common\Idempotency\IdempotencyDecision;
use App\Application\Common\Idempotency\IdempotencyDecisionType;
use App\Domain\Idempotency\Enum\IdempotencyStatus;
use App\Domain\Idempotency\Repository\IdempotencyRecordRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class IdempotencyConcurrentStart
{
    public function __construct(
        private Connection $connection,
        private IdempotencyRecordRepositoryInterface $recordRepository,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @throws Exception
     */
    public function start(
        int $userId,
        string $operation,
        string $idempotencyKey,
        string $requestFingerprint,
    ): IdempotencyDecision {
        $idempotencyKey = trim($idempotencyKey);
        $operation = trim($operation);
        $requestFingerprint = trim($requestFingerprint);

        if ($userId <= 0) {
            throw new \InvalidArgumentException(
                'User ID must be greater than zero.',
            );
        }

        if ($operation === '') {
            throw new \InvalidArgumentException(
                'Idempotency operation cannot be empty.',
            );
        }

        if ($idempotencyKey === '') {
            throw new \InvalidArgumentException(
                'Idempotency key cannot be empty.',
            );
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $requestFingerprint)) {
            throw new \InvalidArgumentException(
                'Request fingerprint must be a SHA-256 hexadecimal hash.',
            );
        }

        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new \RuntimeException(
                sprintf(
                    'User %d was not found.',
                    $userId,
                ),
            );
        }

        /*
         * The UNIQUE(user_id, idempotency_key) constraint is the
         * concurrency boundary.
         *
         * First request:
         *   affected rows = 1
         *
         * Concurrent/repeated request:
         *   affected rows = 0
         *
         * We deliberately do NOT perform:
         *
         *   SELECT
         *   if null INSERT
         *
         * because that is race-prone.
         */
        $affectedRows = $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO idempotency_records (
    user_id,
    idempotency_key,
    operation,
    status,
    request_hash,
    response_status,
    response_body,
    created_at,
    completed_at
) VALUES (
    :user_id,
    :idempotency_key,
    :operation,
    :status,
    :request_hash,
    NULL,
    NULL,
    :created_at,
    NULL
)
ON DUPLICATE KEY UPDATE
    id = id
SQL,
            [
                'user_id' => $userId,
                'idempotency_key' => $idempotencyKey,
                'operation' => $operation,
                'status' => IdempotencyStatus::PROCESSING->value,
                'request_hash' => $requestFingerprint,
                'created_at' => (new \DateTimeImmutable())
                    ->format('Y-m-d H:i:s'),
            ],
        );

        $record = $this->recordRepository->findByKey(
            $userId,
            $idempotencyKey,
        );

        if ($record === null) {
            throw new \RuntimeException(
                'Idempotency record could not be loaded after reservation.',
            );
        }

        if ($record->getOperation() !== $operation) {
            throw new IdempotencyConflict();
        }

        if ($record->getRequestHash() !== $requestFingerprint) {
            throw new IdempotencyConflict();
        }

        if ($record->isCompleted()) {
            return IdempotencyDecision::replay($record);
        }

        if ($record->isFailed()) {
            throw new \DomainException(
                'This idempotency key belongs to a failed operation.',
            );
        }

        if (!$record->isProcessing()) {
            throw new \LogicException(
                'Unknown idempotency record state.',
            );
        }

        /*
         * affectedRows === 1 means THIS request created PROCESSING.
         *
         * affectedRows === 0 means another request already owns it.
         */
        if ($affectedRows === 1) {
            return IdempotencyDecision::execute($record);
        }

        /*
         * Another request owns the processing reservation.
         */
        return IdempotencyDecision::inProgress($record);
    }
}
