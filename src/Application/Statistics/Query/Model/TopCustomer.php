<?php

declare(strict_types=1);
namespace App\Application\Statistics\Query\Model;
final readonly class TopCustomer
{
    public function __construct(public int $customerId, public string $name, public int $orderCount, public string $totalSpent) {}
}
