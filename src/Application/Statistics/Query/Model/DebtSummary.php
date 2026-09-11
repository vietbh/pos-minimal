<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query\Model;
final readonly class DebtSummary
{
    public function __construct(public int $debtCount, public string $originalAmount, public string $collectedAmount, public string $outstandingAmount) {}
}
