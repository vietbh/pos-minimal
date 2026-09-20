<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\PaymentReference;

interface PaymentReferenceRepositoryInterface
{
    public function save(PaymentReference $reference): void;

    public function findByReference(string $reference): ?PaymentReference;

    public function findByReferenceForUpdate(string $reference): ?PaymentReference;

    public function findLatestByOrderId(int $orderId): ?PaymentReference;

    public function findLatestByOrderIdForUpdate(int $orderId): ?PaymentReference;

    public function findLatestPendingBySession(int $sessionId): ?PaymentReference;

    public function findLatestPendingBySessionForUpdate(int $sessionId): ?PaymentReference;
}
