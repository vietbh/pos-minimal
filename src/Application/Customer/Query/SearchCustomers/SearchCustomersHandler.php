<?php

declare(strict_types=1);

namespace App\Application\Customer\Query\SearchCustomers;

use App\Application\Customer\Query\CustomerQueryRepositoryInterface;

final readonly class SearchCustomersHandler
{
    private const MAX_LIMIT = 50;

    public function __construct(
        private CustomerQueryRepositoryInterface $customerQueryRepository,
    ) {
    }

    /**
     * @return list<CustomerSearchResult>
     */
    public function __invoke(
        SearchCustomersInput $input,
    ): array {
        $query = trim($input->query);

        if ($query === '') {
            return [];
        }

        if ($input->limit <= 0) {
            throw new \InvalidArgumentException(
                'Search limit must be greater than zero.',
            );
        }

        $limit = min(
            $input->limit,
            self::MAX_LIMIT,
        );

        return $this->customerQueryRepository->searchCustomers(
            $query,
            $limit,
        );
    }

    /**
     * Returns the default customer list for the customer management page.
     * Search-by-empty-query intentionally remains empty for POS autocomplete.
     *
     * @return list<CustomerSearchResult>
     */
    public function list(int $limit = 10): array
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException('Customer list limit must be greater than zero.');
        }

        return $this->customerQueryRepository->listCustomers(min($limit, self::MAX_LIMIT));
    }
}

