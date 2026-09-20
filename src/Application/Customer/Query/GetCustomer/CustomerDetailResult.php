<?php

declare(strict_types=1);

namespace App\Application\Customer\Query\GetCustomer;

use App\Application\Debt\Query\ListDebts\DebtListItemResult;

final readonly class CustomerDetailResult
{
    /** @param list<DebtListItemResult> $debts @param list<CustomerOrderSummaryResult> $orders */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $phone,
        public ?string $note,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public int $debtCount,
        public string $debtOriginalAmount,
        public string $debtPaidAmount,
        public string $debtRemainingAmount,
        public array $debts,
        public array $orders,
        public int $orderCount,
    ) {
    }
}
