<?php

declare(strict_types=1);

namespace App\Application\Customer\Query;

use App\Application\Customer\Query\GetCustomer\CustomerDetailResult;
use App\Application\Customer\Query\SearchCustomers\CustomerSearchResult;

interface CustomerQueryRepositoryInterface
{
    /**
     * @return list<CustomerSearchResult>
     */
    public function findCustomerDetail(int $id): ?CustomerDetailResult;

    public function searchCustomers(
        string $query,
        int $limit,
    ): array;

    /** @return list<CustomerSearchResult> */
    public function listCustomers(int $limit): array;
}
