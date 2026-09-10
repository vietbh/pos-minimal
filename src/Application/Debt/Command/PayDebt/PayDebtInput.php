<?php

declare(strict_types=1);
namespace App\Application\Debt\Command\PayDebt;
final readonly class PayDebtInput { public function __construct(public int $debtId, public string $amount, public string $idempotencyKey) {} }
