<?php

declare(strict_types=1);
namespace App\Application\Debt\Query;
use App\Application\Debt\Query\GetDebt\DebtDetailResult;
use App\Application\Debt\Query\ListDebts\ListDebtsInput;
use App\Application\Debt\Query\ListDebts\DebtListResult;
interface DebtQueryRepositoryInterface { public function listDebts(ListDebtsInput $input): DebtListResult; public function findDebtById(int $id): ?DebtDetailResult; }
