<?php

declare(strict_types=1);
namespace App\Application\Debt\Command\PayDebt;
final readonly class PayDebtResult { public function __construct(public int $debtId, public int $paymentId, public string $amount, public string $paidAmount, public string $remainingAmount, public string $status) {} }
