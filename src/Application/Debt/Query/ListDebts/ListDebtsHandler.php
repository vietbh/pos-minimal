<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\ListDebts;
use App\Application\Debt\Query\DebtQueryRepositoryInterface;
final readonly class ListDebtsHandler { public function __construct(private DebtQueryRepositoryInterface $repository) {} public function __invoke(ListDebtsInput $input): DebtListResult { return $this->repository->listDebts($input); } }
