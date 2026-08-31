<?php

declare(strict_types=1);

namespace App\Application\Customer\Query;

use App\Application\Customer\Query\SearchCustomers\CustomerSearchResult;

interface CustomerQueryRepositoryInterface
{
    /**
     * @return list<CustomerSearchResult>
     */
    public function searchCustomers(
        string $query,
        int $limit,
    ): array;
}
