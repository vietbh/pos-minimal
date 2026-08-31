<?php

declare(strict_types=1);

namespace App\Application\Customer\Query\SearchCustomers;

final readonly class SearchCustomersInput
{
    public function __construct(
        public string $query,
        public int $limit = 20,
    ) {
    }
}
