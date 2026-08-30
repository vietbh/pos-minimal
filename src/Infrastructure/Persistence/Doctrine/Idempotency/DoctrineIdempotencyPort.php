<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Idempotency;

use App\Application\Common\Idempotency\IdempotencyDecision;
use App\Application\Common\Idempotency\IdempotencyPort;
use App\Domain\Idempotency\Repository\IdempotencyRecordRepositoryInterface;

final readonly class DoctrineIdempotencyPort implements IdempotencyPort
{
    public function __construct(
        private IdempotencyConcurrentStart $concurrentStart,
        private IdempotencyRecordRepositoryInterface $recordRepository,
    ) {
    }

    public function start(
        int $userId,
        string $operation,
        string $idempotencyKey,
        string $requestFingerprint,
    ): IdempotencyDecision {
        return $this->concurrentStart->start(
            userId: $userId,
            operation: $operation,
            idempotencyKey: $idempotencyKey,
            requestFingerprint: $requestFingerprint,
        );
    }

    public function complete(
        IdempotencyDecision $decision,
        int $responseStatus,
        array $responseBody,
    ): void {
        $record = $this->getFreshRecord($decision);

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
        $record = $this->getFreshRecord($decision);

        $record->markFailed(
            $responseStatus,
            $responseBody,
        );

        $this->recordRepository->save($record);
    }

    private function getFreshRecord(
        IdempotencyDecision $decision,
    ): \App\Domain\Idempotency\IdempotencyRecord {
        $record = $decision->record;

        if ($record === null) {
            throw new \LogicException(
                'An idempotency decision must contain a record.',
            );
        }

        $userId = $record->getUser()->getId();

        if ($userId === null) {
            throw new \LogicException(
                'Idempotency record user must have an ID.',
            );
        }

        $freshRecord = $this->recordRepository->findByKey(
            $userId,
            $record->getIdempotencyKey(),
        );

        if ($freshRecord === null) {
            throw new \LogicException(
                'Idempotency record was not found.',
            );
        }

        return $freshRecord;
    }
}
