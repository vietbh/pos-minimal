<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\GetDebt;
final readonly class GetDebtInput { public function __construct(public int $debtId) {} }
