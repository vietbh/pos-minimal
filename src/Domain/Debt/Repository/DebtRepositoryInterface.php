<?php

declare(strict_types=1);

namespace App\Domain\Debt\Repository;

use App\Domain\Debt\Debt;

interface DebtRepositoryInterface
{
    public function save(Debt $debt): void;

    public function findById(int $id): ?Debt;

    public function findByOrderId(int $orderId): ?Debt;

    public function findByCustomerId(int $customerId): array;
}
