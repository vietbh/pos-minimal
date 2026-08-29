<?php

declare(strict_types=1);

namespace App\Application\Common\Idempotency;

interface IdempotencyPort
{
    public function start(
        int $userId,
        string $operation,
        string $idempotencyKey,
        string $requestFingerprint,
    ): IdempotencyDecision;

    /**
     * @param array<string, mixed> $responseBody
     */
    public function complete(
        IdempotencyDecision $decision,
        int $responseStatus,
        array $responseBody,
    ): void;

    /**
     * @param array<string, mixed>|null $responseBody
     */
    public function fail(
        IdempotencyDecision $decision,
        int $responseStatus,
        ?array $responseBody = null,
    ): void;
}
