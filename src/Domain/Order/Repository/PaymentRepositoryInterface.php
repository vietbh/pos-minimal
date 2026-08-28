<?php

declare(strict_types=1);

namespace App\Domain\Order\Repository;

use App\Domain\Order\Payment;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): void;

    public function findById(int $id): ?Payment;

    /**
     * @return list<Payment>
     */
    public function findByOrderId(int $orderId): array;
}
