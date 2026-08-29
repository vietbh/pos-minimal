<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Customer\Customer;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Customer $customer): void
    {
        $this->entityManager->persist($customer);
    }

    public function findById(int $id): ?Customer
    {
        return $this->entityManager
            ->getRepository(Customer::class)
            ->find($id);
    }

    public function findByPhone(string $phone): ?Customer
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->where('c.phone = :phone')
            ->setParameter('phone', $phone)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByPhone(
        string $phone,
        ?int $excludeId = null,
    ): bool {
        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->select('1')
            ->from(Customer::class, 'c')
            ->where('c.phone = :phone')
            ->setParameter('phone', $phone)
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $queryBuilder
                ->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $queryBuilder
                ->getQuery()
                ->getOneOrNullResult() !== null;
    }
}
