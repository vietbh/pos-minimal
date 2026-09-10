<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\GetDebt;
use App\Application\Debt\Query\DebtQueryRepositoryInterface;
final readonly class GetDebtHandler { public function __construct(private DebtQueryRepositoryInterface $repository) {} public function __invoke(GetDebtInput $input): ?DebtDetailResult { return $this->repository->findDebtById($input->debtId); } }
