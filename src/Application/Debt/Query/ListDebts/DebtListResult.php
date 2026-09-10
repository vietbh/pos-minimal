<?php

declare(strict_types=1);
namespace App\Application\Debt\Query\ListDebts;
final readonly class DebtListResult { /** @param list<DebtListItemResult> $items */ public function __construct(public array $items, public int $page, public int $perPage, public int $totalItems) {} public function getTotalPages(): int { return max(1, (int) ceil($this->totalItems / $this->perPage)); } }
