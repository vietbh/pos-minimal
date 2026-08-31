<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Query;

use App\Application\Customer\Query\CustomerQueryRepositoryInterface;
use App\Application\Customer\Query\SearchCustomers\CustomerSearchResult;
use App\Domain\Customer\Customer;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerQueryRepository implements CustomerQueryRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<CustomerSearchResult>
     */
    public function searchCustomers(
        string $query,
        int $limit,
    ): array {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $pattern = '%'.$query.'%';

        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select(
                'c.id AS id',
                'c.name AS name',
                'c.phone AS phone',
            )
            ->from(Customer::class, 'c')
            ->where(
                '(c.name LIKE :query OR c.phone LIKE :query)',
            )
            ->setParameter('query', $pattern)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $results = [];

        foreach ($rows as $row) {
            $results[] = new CustomerSearchResult(
                id: (int) $row['id'],
                name: (string) $row['name'],
                phone: $row['phone'] !== null
                    ? (string) $row['phone']
                    : null,
            );
        }

        return $results;
    }
}
