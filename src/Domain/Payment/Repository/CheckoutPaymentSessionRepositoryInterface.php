<?php

declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\CheckoutPaymentSession;

interface CheckoutPaymentSessionRepositoryInterface
{
    public function save(CheckoutPaymentSession $session): void;
    public function findById(int $id): ?CheckoutPaymentSession;
    public function findByIdForUpdate(int $id): ?CheckoutPaymentSession;
    public function findActiveByKeyForUpdate(string $activeKey): ?CheckoutPaymentSession;
}
