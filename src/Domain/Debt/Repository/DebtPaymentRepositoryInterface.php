<?php

declare(strict_types=1);

namespace App\Domain\Debt\Repository;

use App\Domain\Debt\DebtPayment;

interface DebtPaymentRepositoryInterface
{
    public function save(DebtPayment $payment): void;

    public function findById(int $id): ?DebtPayment;

    /**
     * @return list<DebtPayment>
     */
    public function findByDebtId(int $debtId): array;
}
